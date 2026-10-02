<style>
    #picker-dnd { transition: border-color .2s ease, background-color .2s ease; }
    #picker-dnd:hover { border-color: #93c5fd; background-color: rgba(239, 246, 255, .45); }
    #picker-dnd.dnd-active { border-color: #2563eb; background-color: #eff4ff; }
    #picker-dnd.dnd-active #upload-container { color: #2563eb; }

    /* ---------- 上传前自动压缩 · 开关条 ----------
       默认开启，开关状态只作用于当前这次访问，不做任何持久化。 */
    .compress-bar {
        display: flex; align-items: center; gap: 11px;
        margin-top: 14px; padding: 10px 13px;
        border: 1px solid #dbeafe; border-radius: 10px;
        background-color: #f5f9ff;
        cursor: pointer; -webkit-tap-highlight-color: transparent;
        transition: background-color .2s ease, border-color .2s ease;
    }
    .compress-bar:hover { background-color: #eff6ff; border-color: #bfdbfe; }
    .compress-bar.is-off { background-color: #f9fafb; border-color: #e5e7eb; }
    .compress-bar.is-off:hover { background-color: #f3f4f6; border-color: #d1d5db; }
    .compress-bar.is-disabled { cursor: not-allowed; opacity: .7; }
    .compress-bar.is-disabled:hover { background-color: #f9fafb; border-color: #e5e7eb; }

    .compress-bar__icon {
        flex: 0 0 auto; width: 32px; height: 32px; border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        background-color: #2563eb; color: #fff; font-size: 14px;
        transition: background-color .2s ease;
    }
    .compress-bar.is-off .compress-bar__icon { background-color: #9ca3af; }
    .compress-bar.is-disabled .compress-bar__icon { background-color: #b9bec7; }

    .compress-bar__text { flex: 1 1 auto; min-width: 0; }
    .compress-bar__title { display: block; font-size: 13.5px; font-weight: 600; color: #1f2937; line-height: 1.35; }
    .compress-bar__desc { display: block; font-size: 12px; color: #6b7280; line-height: 1.4; margin-top: 1px; }

    .compress-bar__right { flex: 0 0 auto; display: flex; align-items: center; gap: 9px; }
    .compress-bar__state {
        font-size: 12px; font-weight: 600; white-space: nowrap;
        color: #1d4ed8; background-color: rgba(37, 99, 235, .1);
        padding: 2px 8px; border-radius: 9999px;
        transition: color .2s ease, background-color .2s ease;
    }
    .compress-bar.is-off .compress-bar__state,
    .compress-bar.is-disabled .compress-bar__state { color: #6b7280; background-color: #eef0f3; }

    /* 窄屏（≤414px，绝大多数手机）：状态徽标与右侧开关语义重复，收起它把宽度让给文案。
       实测：三层容器内边距（main 20 + 卡片 24 + 内层面板 20）已吃掉 128px，
       320~390px 实际只有 56~126px 文字宽度，不收起时标题/描述都会被挤成多行。 */
    @media (max-width: 414px) {
        .compress-bar__state { display: none; }
    }

    /* 极窄屏（≤360px，如 iPhone SE 一代）：图标是纯装饰，收掉后标题才不再折行 */
    @media (max-width: 360px) {
        .compress-bar__icon { display: none; }
    }

    /* 开关本体（零依赖纯 CSS，不依赖 Tailwind 新类名） */
    .cswitch { position: relative; flex: 0 0 auto; width: 42px; height: 23px; }
    .cswitch input {
        position: absolute; top: 0; left: 0; width: 100%; height: 100%;
        margin: 0; opacity: 0; z-index: 2; cursor: inherit;
    }
    .cswitch__track {
        position: absolute; top: 0; left: 0; right: 0; bottom: 0;
        border-radius: 9999px; background-color: #cfd4dc;
        transition: background-color .22s ease; pointer-events: none;
    }
    .cswitch__track::after {
        content: ''; position: absolute; top: 2px; left: 2px;
        width: 19px; height: 19px; border-radius: 50%; background-color: #fff;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .18);
        transition: transform .22s cubic-bezier(.4, 0, .2, 1);
    }
    .cswitch input:checked + .cswitch__track { background-color: #2563eb; }
    .cswitch input:checked + .cswitch__track::after { transform: translateX(19px); }
    .cswitch input:focus-visible + .cswitch__track { box-shadow: 0 0 0 3px rgba(37, 99, 235, .35); }
    .cswitch input:disabled + .cswitch__track { background-color: #e5e7eb; }
    .cswitch input:disabled:checked + .cswitch__track { background-color: #93b4f5; }

    /* 文件卡片上的压缩收益标识 */
    .compress-saved { color: #059669; font-weight: 600; }
</style>
<div class="pb-6 h-full">
    <input type="file" id="picker" name="file" class="hidden" accept="{{ implode(',', array_map(fn ($ext) => '.'.$ext, $_group->configs->get(\App\Enums\GroupConfigKey::AcceptedFileSuffixes))) }}" multiple>

    <div class="mb-4 p-5 bg-white rounded-xl border border-gray-200 shadow-sm">
        <h1 class="flex items-center gap-2 text-xl font-semibold text-gray-800 mb-2"><span class="inline-block w-1 h-5 rounded-full bg-blue-600"></span>上传图片</h1>
        <p class="text-gray-500 text-sm">
            最大可上传 {{ \App\Utils::formatSize($_group->configs->get(\App\Enums\GroupConfigKey::MaximumFileSize) * 1024) }} 的图片，上传队列最多
            {{ $_group->configs->get(\App\Enums\GroupConfigKey::ConcurrentUploadNum) }}
            张。本站已托管 {{ \App\Models\Image::query()->count() }} 张图片。
        </p>

        {{-- 上传前自动压缩开关：每次访问都默认开启，切换只影响本次上传，不做持久化 --}}
        <label class="compress-bar" id="compress-bar">
            <span class="compress-bar__icon"><i class="fas fa-compress-alt"></i></span>
            <span class="compress-bar__text">
                <span class="compress-bar__title">上传前压缩</span>
                <span class="compress-bar__desc" id="compress-desc">小图自动跳过</span>
            </span>
            <span class="compress-bar__right">
                <span class="compress-bar__state" id="compress-state">已开启</span>
                <span class="cswitch">
                    <input type="checkbox" id="compress-toggle" checked aria-label="上传前自动压缩">
                    <span class="cswitch__track"></span>
                </span>
            </span>
        </label>

        <div class="mt-3 rounded-lg border-2 border-dotted border-gray-300 w-full h-full" id="picker-dnd" onclick="$('#picker').click()">
            <div id="upload-container" class="relative group flex flex-col justify-center items-center p-2 w-full h-full min-h-[150px] sm:min-h-[340px] space-y-4 text-gray-500 cursor-pointer">
                <i id="clear" class="fas fa-times absolute top-1 right-1 w-8 h-8 flex justify-center items-center cursor-pointer text-xl text-center hidden group-hover:block text-gray-400 hover:text-gray-500"></i>
                <p id="upload-all" title="点我上传全部"><i class="fas fa-cloud-upload-alt text-6xl text-blue-500 hover:text-blue-600"></i></p>
                <p class="text-md text-center">拖拽文件到这里，支持多文件同时上传<br/>点击上面的图标上传全部已选择文件</p>
                <p id="compress-progress" class="text-xs text-center" style="display:none;color:#2563eb;font-weight:600;"></p>
            </div>
            <div id="upload-preview" class="flex m-2 hidden"></div>
        </div>
    </div>

    <div id="links-container" class="hidden mb-4 p-5 bg-white rounded-xl border border-gray-200 shadow-sm relative">
        <!-- copy-all / clear-all 改为顶行常驻布局，避免绝对定位与下方 tabs 重叠误触 -->
        <div class="flex justify-end gap-2 mb-2">
            <span id="copy-all" class="px-3 py-1 rounded-full text-xs font-medium text-blue-600 bg-blue-50 hover:bg-blue-100 cursor-pointer">复制全部</span>
            <span id="clear-all" class="px-3 py-1 rounded-full text-xs text-gray-600 bg-gray-100 cursor-pointer hover:bg-gray-200">清除</span>
        </div>
        <div id="link-tabs" class="flex flex-nowrap overflow-scroll scrollbar-none text-sm">
            <a href="javascript:void(0)" data-tab-name="url" class="hover:bg-blue-50 flex justify-center items-center px-8 py-2 border-b-2 border-blue-500 text-blue-600 active">URL</a>
            <a href="javascript:void(0)" data-tab-name="html" class="hover:bg-gray-50 flex justify-center items-center px-8 py-2 border-b-2 border-transparent">HTML</a>
            <a href="javascript:void(0)" data-tab-name="bbcode" class="hover:bg-gray-50 flex justify-center items-center px-8 py-2 border-b-2 border-transparent">BBCode</a>
            <a href="javascript:void(0)" data-tab-name="markdown" class="hover:bg-gray-50 flex justify-center items-center px-8 py-2 border-b-2 border-transparent">Markdown</a>
            <a href="javascript:void(0)" data-tab-name="markdown_with_link" class="hover:bg-gray-50 flex justify-center items-center px-8 py-2 border-b-2 border-transparent whitespace-nowrap">Markdown with link</a>
            <a href="javascript:void(0)" data-tab-name="thumbnail_url" class="hover:bg-gray-50 flex justify-center items-center px-8 py-2 border-b-2 border-transparent whitespace-nowrap">Thumbnail url</a>
        </div>
        <div id="links" class="mt-2">
            <div data-tab="url" class="space-y-2"></div>
            <div data-tab="html" class="hidden space-y-2"></div>
            <div data-tab="bbcode" class="hidden space-y-2"></div>
            <div data-tab="markdown" class="hidden space-y-2"></div>
            <div data-tab="markdown_with_link" class="hidden space-y-2"></div>
            <div data-tab="thumbnail_url" class="hidden space-y-2"></div>
        </div>
    </div>
</div>

<x-modal id="preview-modal">
    <div class="rounded-lg overflow-hidden">
        <img class="w-full h-full object-cover" src="">
    </div>
</x-modal>

<script type="text/html" id="image-preview-tpl">
    <div data-id="__id__" class="w-full flex items-center p-2 mb-2 rounded-md relative bg-gray-50 overflow-hidden">
        <div class="absolute inset-0">
            <div class="w-[0%] h-full bg-gray-200 opacity-70 upload-progress"></div>
        </div>
        <div class="relative flex w-full">
            <div class="w-10 h-10 bg-gray-200 rounded-lg cursor-pointer overflow-hidden">
                <img class="w-full h-full object-cover" data-operate="preview" src="__src__">
            </div>
            <div class="flex justify-end flex-col ml-2 w-[80%] opacity-70">
                <p class="text-sm truncate">__name__</p>
                <p class="text-xs truncate">
                    <span>__info__</span>, <span class="upload-info">等待上传</span>
                </p>
            </div>
        </div>
        <div class="absolute right-2 flex space-x-2">
            <a href="javascript:void(0)" data-operate="remove" class="flex justify-center items-center block shadow-sm w-10 h-10 rounded-full text-gray-600 bg-gray-100 hover:bg-gray-200 aspect-w-1 aspect-h-1"><i class="fas fa-times"></i></a>
            <a href="javascript:void(0)" data-operate="upload" class="flex justify-center items-center block shadow-sm w-10 h-10 rounded-full text-gray-600 bg-gray-100 hover:bg-gray-200 aspect-w-1 aspect-h-1"><i class="fas fa-upload"></i></a>
        </div>
    </div>
</script>
@push('scripts')
    <script src="{{ asset('js/blueimp-file-upload/jquery.ui.widget.js') }}"></script>
    <script src="{{ asset('js/blueimp-file-upload/jquery.iframe-transport.js') }}"></script>
    <script src="{{ asset('js/blueimp-file-upload/jquery.fileupload.js') }}"></script>
    <script src="{{ asset('js/blueimp-load-image/load-image.all.min.js') }}"></script>
    <script src="{{ asset('js/clipboard/clipboard.min.js') }}"></script>
    <script src="{{ asset('js/spark-md5/spark-md5.min.js') }}"></script>
@endpush
@push('scripts')
    <script>
        let allowSuffixes = @json($_group->configs->get(\App\Enums\GroupConfigKey::AcceptedFileSuffixes));
        let maxSize = {{ $_group->configs->get(\App\Enums\GroupConfigKey::MaximumFileSize) * 1024 }};
        let pastedAction = '{{ Auth::check() ? Auth::user()->configs->get(\App\Enums\UserConfigKey::PastedAction) : \App\Enums\PastedAction::Waiting }}';
    </script>
    <script>
        (new ClipboardJS('#copy-all', {
            text: function(trigger) {
                let text = '';
                $('[data-tab="' + $('#link-tabs a.active').data('tab-name') + '"] p').each(function (i) {
                    if (i !== 0) {
                        text += '\r\n';
                    }
                    text += $(this).text();
                });
                return text;
            }
        })).on('success', function(e) {
            if (! $(e.trigger).attr('disabled')) {
                let text = $(e.trigger).text();
                $(e.trigger).attr('disabled', true).text('复制成功');
                setTimeout(function () {
                    $(e.trigger).attr('disabled', false).text(text);
                }, 1000);
            }
        }).on('error', function(e) {
            toastr.warning('复制失败')
        });
    </script>
    <script>
        const UPLOAD_WAITING = 0; // 等待上传
        const UPLOAD_SUCCESS = 1; // 上传成功
        const UPLOAD_ERROR = 2; // 上传失败
        let $previews = $('#upload-preview');
        let $links = $('#links-container');
        let $picker = $('#picker');
        let queue = []; // 文件队列
        let excludes = ['psd', 'tif']; // 排除支持预览的格式

        // ---- 直传（OpenList S3）----
        const CUSTOM_STRATEGY_KEY = {{ \App\Enums\StrategyKey::Custom }};
        // 计算文件 md5（SparkMD5）与 sha1（crypto.subtle）
        const calcFileHash = (blob) => new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => {
                const buffer = reader.result;
                try {
                    const md5 = SparkMD5.ArrayBuffer.hash(buffer);
                    crypto.subtle.digest('SHA-1', buffer).then(shaBuf => {
                        const sha1 = Array.from(new Uint8Array(shaBuf)).map(b => b.toString(16).padStart(2, '0')).join('');
                        resolve({ md5, sha1 });
                    }).catch(reject);
                } catch (e) {
                    reject(e);
                }
            };
            reader.onerror = reject;
            reader.readAsArrayBuffer(blob);
        });
        // 获取 csrf token
        const csrfToken = () => $('meta[name="csrf-token"]').attr('content');
        // 判断当前策略是否为 OpenList S3
        const isDirectStrategy = () => parseInt($('#strategy-selected').data('key')) === CUSTOM_STRATEGY_KEY;
        // 成功回调（复用 done 的处理逻辑）
        const handleUploadSuccess = (data, response) => {
            if ({{ (Auth::check() && Auth::user()->configs->get(\App\Enums\UserConfigKey::IsAutoClearPreview)) ? 1 : 0 }}) {
                delete queue[data.$preview.data('id')];
                data.$preview.remove();
            } else {
                setStatus(data, UPLOAD_SUCCESS);
                data.$preview.attr('uploaded', true);
            }
            let links = response.data.links;
            for (let key in links) {
                $('#links [data-tab="' + key + '"]').append('<p class="whitespace-nowrap select-all mt-1 bg-gray-50 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 cursor-pointer overflow-scroll scrollbar-none">' + links[key].toString() + '</p>');
            }
            $links.show();
            utils.setCapacityProgress(response.data.size);
        };
        // 读取图片的真实显示尺寸（现代浏览器已按 EXIF 方向解码，得到的就是「看起来的」宽高）；
        // 读不出来时返回 null，由调用方决定兜底值
        const probeImageSize = (file) => new Promise((resolve) => {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = () => {
                URL.revokeObjectURL(url);
                const width = img.naturalWidth || img.width;
                const height = img.naturalHeight || img.height;
                resolve(width > 0 && height > 0 ? { width, height } : null);
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                resolve(null);
            };
            img.src = url;
        });
        // 随表单提交的尺寸（直传绕过服务器、服务器拿不到字节流，由前端提供）
        const readImageSize = (file) => probeImageSize(file)
            .then((size) => size ? [size.width, size.height] : [400, 400]);
        // 直传：presign → PUT → confirm（绕过图床服务器）
        const directUpload = (guid) => {
            const data = queue[guid];
            if (! data) return;
            data.$preview.find('[data-operate="upload"]').hide();
            setStatus(data, UPLOAD_WAITING, '计算校验值...');
            calcFileHash(data.files[0]).then(({ md5, sha1 }) => {
                const preForm = new FormData();
                preForm.append('strategy_id', $('#strategy-selected').data('id'));
                preForm.append('filename', data.files[0].name);
                preForm.append('extension', data.files[0].name.split('.').pop().toLowerCase());
                preForm.append('size', data.files[0].size);
                preForm.append('mimetype', data.files[0].type || 'image/webp');
                preForm.append('md5', md5);
                preForm.append('sha1', sha1);
                setStatus(data, UPLOAD_WAITING, '获取直传地址...');
                return axios.post('{{ route('upload.presign') }}', preForm, {
                    headers: { 'X-CSRF-TOKEN': csrfToken() }
                }).then(resp => {
                    if (! resp.data.status) throw new Error(resp.data.message);
                    return {
                        upload_url: resp.data.data.upload_url,
                        pathname: resp.data.data.pathname,
                        existing: resp.data.data.existing,
                        md5, sha1,
                    };
                });
            }).then(({ upload_url, pathname, existing, md5, sha1 }) => {
                // 已存在相同文件（去重）则无需再 PUT
                if (existing) {
                    return { pathname, md5, sha1 };
                }
                setStatus(data, UPLOAD_WAITING, '直传中...');
                return fetch(upload_url, { method: 'PUT', body: data.files[0] })
                    .then(r => {
                        if (! r.ok) throw new Error('直传失败(' + r.status + ')');
                        return { pathname, md5, sha1 };
                    });
            }).then(({ pathname, md5, sha1 }) => {
                setStatus(data, UPLOAD_WAITING, '确认上传...');
                return confirmUpload(data, { pathname, md5, sha1 });
            }).catch(e => {
                setStatus(data, UPLOAD_ERROR, e.message || '直传失败');
                data.$preview.find('[data-operate="upload"]').show();
            });
        };
        const confirmUpload = (data, meta) => {
            const conForm = new FormData();
            conForm.append('strategy_id', $('#strategy-selected').data('id'));
            conForm.append('pathname', meta.pathname);
            conForm.append('filename', data.files[0].name);
            conForm.append('extension', data.files[0].name.split('.').pop().toLowerCase());
            conForm.append('size', data.files[0].size);
            conForm.append('mimetype', data.files[0].type || 'image/webp');
            conForm.append('md5', meta.md5);
            conForm.append('sha1', meta.sha1);
            // 读取最终文件（压缩后 webp 或原图）的真实尺寸，随 confirm 提交
            return readImageSize(data.files[0]).then(([width, height]) => {
                conForm.append('dimensions[]', width);
                conForm.append('dimensions[]', height);
                return axios.post('{{ route('upload.confirm') }}', conForm, {
                    headers: { 'X-CSRF-TOKEN': csrfToken() }
                }).then(resp => {
                    if (! resp.data.status) throw new Error(resp.data.message);
                    handleUploadSuccess(data, resp.data);
                    return resp.data;
                });
            });
        };

        // ---- 前端压缩配置 ----
        // 所有用户（游客与登录用户）统一默认开启。开关状态只对当前这次访问有效：
        // 不写 localStorage、不写账号配置，刷新页面即回到「默认开启」。
        // 缩放规则见 targetSize()：常规长边上限沿用 1920（普通照片的压缩效果与之前一致），
        // 额外对长宽比异常的图片做「短边保底」，避免长图被压成一条糊线
        const compressLongEdge = 1920;        // 常规长边上限
        const compressMinShortEdge = 720;     // 短边保底：长图/长截图不许被压到低于此值
        const compressSafePixels = 12e6;      // 安全天花板：画布总面积（部分移动端 canvas 上限约 16MP）
        const compressSafeEdge = 8192;        // 安全天花板：画布单边像素
        const compressQuality = 0.65;       // 有损编码质量
        const minCompressSize = 100 * 1024; // 小于 100KB 的图片不压缩，避免无谓编码
        const compressibleTypes = ['jpg', 'jpeg', 'png']; // 仅这些格式参与压缩（gif/ico/psd/tif/bmp 保持原样）
        const compressConcurrency = 2;      // 同时最多处理 2 张：手机上一次并行编码 20 张大图会卡顿甚至崩溃

        let compressEnabled = true;   // 默认开启（每次页面加载都重置为此值，不读取任何持久化存储）
        let compressTipShown = false; // 「已关闭压缩」的一次性提醒

        /**
         * 候选编码格式，按优先级排列：WebP 体积最优，JPEG 作兼容性兜底。
         * 背景：Safari 从不支持用 canvas 编码 WebP，而按 HTML 规范，请求了不受支持的格式时
         * 浏览器必须「静默返回 PNG」—— 不抛异常、不返回 null，只是 blob.type 变成了 image/png。
         * 因此能力探测不能靠 UA 嗅探，只能实测「要什么、拿到的是不是它」。
         */
        const formatCandidates = [
            { mime: 'image/webp', exts: ['webp'] },
            { mime: 'image/jpeg', exts: ['jpg', 'jpeg'], opaque: true } // JPEG 无透明通道，需先铺白底
        ];

        // 编码能力实测（toDataURL 的 data URI 前缀与 blob.type 同源，可同步探测）
        const encodeSupport = {};
        function canEncode(mime) {
            if (mime in encodeSupport) {
                return encodeSupport[mime];
            }
            let ok = false;
            try {
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = 2;
                const ctx = canvas.getContext('2d');
                ctx.fillStyle = 'rgba(0, 128, 255, .5)';
                ctx.fillRect(0, 0, 1, 1);
                ok = canvas.toDataURL(mime).indexOf('data:' + mime) === 0;
            } catch (e) {
                ok = false;
            }
            return (encodeSupport[mime] = ok);
        }

        // 当前可用格式 = 浏览器能编码 ∩ 当前用户组接受该后缀
        const groupSuffixes = (Array.isArray(allowSuffixes) ? allowSuffixes : [])
            .map(function (s) { return String(s).toLowerCase(); });
        const allowedFormats = formatCandidates.reduce(function (list, f) {
            const ext = f.exts.filter(function (e) { return groupSuffixes.indexOf(e) !== -1; })[0];
            if (! ext || ! canEncode(f.mime)) {
                return list;
            }
            list.push({ mime: f.mime, ext: ext, opaque: !! f.opaque });
            return list;
        }, []);
        // 开关不可用的原因（空字符串表示可用）。只有「一个目标格式都用不了」才禁用，
        // 浏览器不支持 WebP 时会自动改用 JPEG，不再直接关掉开关。
        const compressDisabledReason = allowedFormats.length
            ? ''
            : (canEncode('image/webp') || canEncode('image/jpeg')
                ? '本站未开放 WebP/JPEG，已关闭'
                : '浏览器不支持，已关闭');

        /**
         * 客观能力判断：这张图「能不能」压缩。与用户意愿无关。
         * @param {File} file
         * @returns {boolean}
         */
        function canCompress(file) {
            if (! allowedFormats.length) return false;                // 没有可用的目标格式
            const ext = file.name.substr(file.name.lastIndexOf('.') + 1).toLowerCase();
            if (compressibleTypes.indexOf(ext) === -1) return false;  // 仅 jpg/jpeg/png
            if (file.size < minCompressSize) return false;            // 小图跳过，避免无谓编码
            return true;
        }

        /**
         * 抽样判断图片是否含透明像素。
         * 整图 getImageData 对 4000×3000 要吃掉上百 MB，必须降采样后再读。
         * 只有 png 需要检查：jpeg 本身没有 alpha 通道。
         */
        function hasTransparency(source) {
            try {
                const s = 64;
                const canvas = document.createElement('canvas');
                canvas.width = canvas.height = s;
                const ctx = canvas.getContext('2d');
                ctx.clearRect(0, 0, s, s);
                ctx.drawImage(source, 0, 0, s, s);
                const data = ctx.getImageData(0, 0, s, s).data;
                for (let i = 3; i < data.length; i += 4) {
                    if (data[i] < 250) return true;
                }
            } catch (e) {
                return false; // 读像素失败时按不透明处理，不阻断压缩
            }
            return false;
        }

        /**
         * 计算压缩后的目标尺寸（保持长宽比，只缩不放）。
         *
         * 旧方案是「整张图塞进 1920×1920 方框」，对普通照片没问题，但长宽比异常的图会遭殃：
         * 10000×1000 的长图会被压成 1920×192，短边只剩 192px，放大看全是马赛克。
         * 现在改为三级约束：
         *   1) 常规预算：长边 ≤ 1920，与旧方案一致，普通照片的压缩效果不变；
         *   2) 短边保底：缩放后短边若会低于 720（原图短边本身就不足 720 的不再打折），
         *      则按短边反推缩放比 —— 宁可让长边超出预算，也不把图压到看不清；
         *   3) 安全天花板：总像素 ≤ 12MP、单边 ≤ 8192，避免保底把画布撑到超出浏览器能力
         *      （部分移动端 canvas 上限约 16MP，超了只会得到一张全白的画布）。
         * @param {number} width 原图宽
         * @param {number} height 原图高
         * @returns {Object|null} 目标尺寸 {width, height}；返回 null 表示无需缩放（仅压画质）
         */
        function targetSize(width, height) {
            const longEdge = Math.max(width, height);
            const shortEdge = Math.min(width, height);
            // 1) 常规预算
            let scale = Math.min(1, compressLongEdge / longEdge);
            // 2) 短边保底（原图短边本身就小的话 floor 等于原短边，这一步只保证「不打折扣」）
            const floor = Math.min(compressMinShortEdge, shortEdge);
            if (shortEdge * scale < floor) {
                scale = floor / shortEdge;
            }
            // 3) 安全天花板：保底会把 scale 抬回去，这里再夹一次
            scale = Math.min(scale, Math.sqrt(compressSafePixels / (width * height)), compressSafeEdge / longEdge);
            if (! (scale < 1)) {
                return null;
            }
            // 取 ceil：宁可多 1px，也别让取整后的那一侧反过来变成更紧的约束
            return {
                width: Math.max(1, Math.ceil(width * scale)),
                height: Math.max(1, Math.ceil(height * scale)),
            };
        }

        /**
         * 按当前开关状态把图片处理为最终要上传的对象。
         * 依次尝试可用格式；开关关闭、无需压缩、编码失败或压缩后反而变大时，一律回退原图。
         * @param {File} file 原图
         * @param {Function} callback (blob, compressed:boolean, format:object|null) => void
         */
        function compressImage(file, callback) {
            // 主观意愿：本次被用户关掉了
            if (! compressEnabled) {
                return callback(file, false, null);
            }
            // 客观能力：任一条件不满足都回退原图
            if (! canCompress(file)) {
                return callback(file, false, null);
            }
            // 先量出真实尺寸，才能给出「非方形」的缩放约束；量不出来就按原图上传，
            // 绝不猜尺寸 —— 猜错会把图压糊。
            probeImageSize(file).then(function (size) {
                if (! size) {
                    return callback(file, false, null);
                }
                const target = targetSize(size.width, size.height);
                loadImage(file, function (img) {
                    if (! img || img.type === 'error') {
                        return callback(file, false, null);
                    }
                    let checkOpaque = file.name.substr(file.name.lastIndexOf('.') + 1).toLowerCase() === 'png';

                    // 依次尝试候选格式：某个格式失败（不支持 / 没变小）就换下一个，全不行则保留原图
                    const attempt = function (index) {
                        const format = allowedFormats[index];
                        if (! format) {
                            return callback(file, false, null);
                        }
                        // JPEG 不支持透明通道，规范要求把透明区合成到黑色 —— 先铺白底避免出现黑块
                        if (format.opaque && checkOpaque) {
                            checkOpaque = false;
                            if (hasTransparency(img)) {
                                const ctx = img.getContext('2d');
                                if (ctx) {
                                    ctx.globalCompositeOperation = 'destination-over';
                                    ctx.fillStyle = '#ffffff';
                                    ctx.fillRect(0, 0, img.width, img.height);
                                    ctx.globalCompositeOperation = 'source-over';
                                }
                            }
                        }
                        img.toBlob(function (blob) {
                            // 兜底校验：请求的格式不被支持时 blob.type 会变成 image/png（Safari 静默回退）
                            if (! blob || blob.type !== format.mime) {
                                return attempt(index + 1);
                            }
                            // 压缩后没有变小 → 试下一个格式，都不行就保留原图
                            if (blob.size >= file.size) {
                                return attempt(index + 1);
                            }
                            // 改名为目标后缀，使服务端按该格式存储（后缀须与 blob.type 一致）
                            blob.name = file.name.replace(/\.[^.]+$/, '') + '.' + format.ext;
                            blob.lastModified = file.lastModified;
                            callback(blob, true, format);
                        }, format.mime, compressQuality);
                    };
                    attempt(0);
                }, {
                    // 上限即目标尺寸（保持长宽比）；target 为 null 时用原尺寸作上限，等价于不缩放。
                    // blueimp 只在超限时缩小、从不放大，逐级降采样让大幅缩小不至于出现锯齿。
                    maxWidth: target ? target.width : size.width,
                    maxHeight: target ? target.height : size.height,
                    downsamplingRatio: 0.5,
                    imageSmoothingQuality: 'high',
                    canvas: true,
                    orientation: true,
                    meta: true
                });
            });
        }

        // ---- 压缩任务队列：限制并发，避免一次选中 20 张大图把手机压垮 ----
        const compressQueue = [];
        let compressActive = 0;

        function scheduleCompress(task) {
            compressQueue.push(task);
            pumpCompress();
        }

        function pumpCompress() {
            while (compressActive < compressConcurrency && compressQueue.length) {
                const task = compressQueue.shift();
                compressActive++;
                let released = false;
                task(function release() {
                    if (released) return; // 防止重复释放，否则并发额度会虚增
                    released = true;
                    compressActive--;
                    pumpCompress();
                });
            }
        }

        // ---- 处理进度提示：并发受限后队列会排一会儿，需要让用户看到反馈 ----
        function activeQueueItems() {
            return Object.keys(queue).map(function (k) { return queue[k]; }).filter(Boolean);
        }

        function refreshCompressProgress() {
            const items = activeQueueItems();
            const pending = items.filter(function (d) { return d.pending; }).length;
            const $el = $('#compress-progress');
            if (! pending) {
                return $el.hide().text('');
            }
            $el.text('处理中 ' + (items.length - pending) + '/' + items.length).show();
        }

        /** 刷新开关条的视觉状态与文案（文案刻意精简，手机端只占两行） */
        function applyCompressUI() {
            const $bar = $('#compress-bar');
            if (compressDisabledReason) {
                $bar.addClass('is-disabled').removeClass('is-off');
                $('#compress-state').text('不可用');
                $('#compress-desc').text(compressDisabledReason);
                return;
            }
            $bar.toggleClass('is-off', ! compressEnabled);
            $('#compress-state').text(compressEnabled ? '已开启' : '已关闭');
            $('#compress-desc').text(compressEnabled ? '小图自动跳过' : '本次保留原图上传');
        }

        /** 用给定的缩略图 src 渲染（或重建）文件卡片 */
        function push(data, src) {
            // 该项已被移除（异步压缩/缩略图期间用户点了删除）→ 不再渲染
            if (! queue[data.guid]) return;
            const info = data.sizeInfo || { origin: data.originFile.size, final: data.originFile.size, compressed: false };
            let sizeText;
            if (info.compressed) {
                const saved = Math.max(0, Math.round((1 - info.final / info.origin) * 100));
                // 只留「多大 → 多大」，格式后缀这类细节不往卡片上堆（手机端一行放不下）
                sizeText = utils.formatSize(info.origin)
                    + ' → <span class="compress-saved">' + utils.formatSize(info.final) + ' · 省 ' + saved + '%</span>';
            } else {
                sizeText = utils.formatSize(info.final) + ' · 原图上传';
            }
            const $new = $(
                $('#image-preview-tpl').html()
                    .replace(/__id__/g, data.guid)
                    .replace(/__src__/g, src)
                    .replace(/__name__/g, data.originFile.name.replace(/\$/g, '$$$$'))
                    .replace(/__info__/g, sizeText)
            );
            // 重建时插回原位置，避免切换开关后队列顺序错乱
            if (data.$preview && data.$preview.length) {
                data.$preview.before($new);
                data.$preview.remove();
            } else {
                $previews.append($new).show();
            }
            data.$preview = $new;
            setStatus(data, UPLOAD_WAITING);
            refreshCompressProgress();

            // 粘贴来源且配置为「直接上传」时自动开始
            if (data.from === 'paste' && pastedAction === '{{ \App\Enums\PastedAction::Upload }}') {
                submitItem(data.guid);
            }
        }

        /** 生成缩略图后渲染卡片（token 用于丢弃被更新一轮渲染取代的过期结果） */
        function renderPreview(data, token, done) {
            loadImage(data.files[0], function (img) {
                if (token !== undefined && token !== data.renderToken) {
                    if (done) done();
                    return;
                }
                if (! img || img.type === 'error') {
                    toastr.error('文件 ' + data.originFile.name + ' 缩略图生成失败');
                    console.error('Error loading image file');
                    if (done) done();
                    return;
                }
                push(data, img.toDataURL());
                if (done) done();
            }, {
                maxWidth: 200,
                maxHeight: 200,
                meta: true,
                orientation: true,
                canvas: true
            });
        }

        /** 用原图按当前开关状态重新处理该队列项，并重建卡片（走并发受限的任务队列） */
        function requeue(data) {
            const token = ++data.renderToken;
            const under = compressEnabled; // 记录本次是按哪个开关状态处理的，避免重复排队
            data.pending = true;
            refreshCompressProgress();
            scheduleCompress(function (release) {
                const finish = function () {
                    data.pending = false;
                    refreshCompressProgress();
                    release();
                    // 「上传全部」时被挂起的项：一旦处理完立即自动开始上传
                    if (data.autoSubmit && queue[data.guid] && ! data.started && data.status !== UPLOAD_SUCCESS) {
                        submitItem(data.guid);
                    }
                };
                // 排队期间该项可能已被删除、或又切换了一次开关 → 直接让出并发名额
                if (token !== data.renderToken || ! queue[data.guid]) {
                    return finish();
                }
                compressImage(data.originFile, function (blob, compressed, format) {
                    // 期间又切换了开关、或该项已被移除 → 丢弃过期结果
                    if (token !== data.renderToken || ! queue[data.guid]) {
                        return finish();
                    }
                    data.files[0] = blob;
                    data.processedUnder = under;
                    data.sizeInfo = {
                        origin: data.originFile.size,
                        final: blob.size,
                        compressed: compressed,
                        format: format ? format.ext : null
                    };
                    renderPreview(data, token, finish);
                });
            });
        }

        /** 切换开关后，把尚未开始上传的队列项重新处理一遍 */
        function recompressPending() {
            Object.keys(queue).forEach(function (guid) {
                const data = queue[guid];
                if (! data || ! data.originFile) return;
                if (data.started || data.status === UPLOAD_SUCCESS) return;
                // 已按当前开关状态处理过（含正在排队/处理中的）就不必重来，
                // 否则并发受限时反复切换会堆出一长串重复任务
                if (data.processedUnder === compressEnabled) return;
                // 不参与压缩的格式（psd/tif）保持原样
                const name = data.originFile.name;
                if (excludes.indexOf(name.substring(name.lastIndexOf('.') + 1).toLowerCase()) !== -1) return;
                requeue(data);
            });
        }

        /** 统一提交入口：先标记已开始上传（此后不再重压），再按储存策略选择直传或原生上传 */
        function submitItem(guid) {
            const data = queue[guid];
            if (! data || data.started) return;
            data.started = true;
            if (isDirectStrategy()) {
                directUpload(guid);
            } else {
                data.submit();
            }
        }

        /**
         * 请求上传。并发受限后「等待压缩」会持续一段时间，
         * 直接跳过会让「上传全部」漏掉大半文件，改为挂起、处理完自动提交。
         */
        function requestSubmit(guid) {
            const data = queue[guid];
            if (! data || data.started || data.status === UPLOAD_SUCCESS) return;
            data.autoSubmit = true;
            if (data.pending) return; // 处理完由 finish() 自动提交
            submitItem(guid);
        }
        /**
         * 设置状态
         * @param data
         * @param status
         * @param message
         */
        const setStatus = (data, status, message) => {
            queue[data.guid].status = data.status = status;
            let $info = data.$preview.find('.upload-info');
            $info.removeClass('text-green-500 text-red-500')
            let msg = '';
            switch (status) {
                case UPLOAD_WAITING:
                    msg = '等待上传';
                    break;
                case UPLOAD_SUCCESS:
                    msg = '上传成功';
                    $info.addClass('text-green-800');
                    break;
                case UPLOAD_ERROR:
                    msg = '上传失败';
                    $info.addClass('text-red-500')
                    break;
            }
            $info.text(message ? message : msg);
        }
        $picker.fileupload({
            url: '{{ route('upload') }}',
            autoUpload: false,
            dataType: 'json',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            limitMultiFileUploads: 1,
            limitConcurrentUploads: {{ $_group->configs->get(\App\Enums\GroupConfigKey::ConcurrentUploadNum, 3) }},
            pasteZone: $(document),
            dropZone: $('#picker-dnd'),
            formData: (form) => {
                return [{name: 'strategy_id', value: $('#strategy-selected').data('id')}];
            },
            paste: (e, data) => {
                let files = [];
                $.each(data.files, function (index, file) {
                    let name = new Date().getTime().toString();
                    files[index] = new File([file], name + "." + file.name.substr(file.name.lastIndexOf('.') + 1), {
                        type: file.type,
                        lastModified: file.lastModified,
                    });
                });
                data.files = files;
                data.from = 'paste';
            },
            add: (e, data) => { // Return true to continue adding, otherwise terminate
                let file = data.files[0];
                let ext = file.name.substr(file.name.lastIndexOf('.') + 1);
                if (allowSuffixes.indexOf(ext.toLowerCase()) === -1) {
                    toastr.warning(`不支持的图片格式 ${file.name}`);
                    return true;
                }
                if (file.size > maxSize) {
                    // 上限按原图判断（保护服务端），文案写明是「原图」超限，避免与压缩混淆
                    toastr.warning(`图片 ${file.name} 原图 ${utils.formatSize(file.size)} 超出上传上限 ${utils.formatSize(maxSize)}`);
                    return true;
                }
                let guid = utils.guid();
                data.guid = guid;
                // 始终保留原图：切换压缩开关后需要用它重新处理
                data.originFile = file;
                data.started = false;     // 已开始上传的项不再重压
                data.pending = false;     // 正在压缩中
                data.autoSubmit = false;  // 用户要求上传，但还要等压缩完成
                data.renderToken = 0;     // 用于丢弃过期的异步压缩结果
                data.sizeInfo = { origin: file.size, final: file.size, compressed: false };
                queue[guid] = data;

                if (excludes.indexOf(file.name.substring(file.name.lastIndexOf(".") + 1).toLowerCase()) !== -1) {
                    // 不压缩的格式（psd/tif）：直接按原图入队
                    push(data, 'data:image/png;base64,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', data.files[0].size);
                } else {
                    // 按当前开关状态处理：默认开启压缩，失败或无需压缩时自动回退原图
                    requeue(data);
                }
            },
            send: (e, data) => {
                data.$preview.find('[data-operate="upload"]').hide();
            },
            progress: (e, data) => {
                let progress = parseInt(data.loaded / data.total * 100, 10);
                let $uploadInfo = data.$preview.find('.upload-info');
                let $uploadProgress = data.$preview.find('.upload-progress');
                let rate = progress + '%';
                $uploadInfo.text('上传中...' + rate);
                $uploadProgress.css('width', rate);
            },
            done: (e, data) => {
                let response = data.result;
                if (response.status) {
                    // 如果开启了自动清除缩略图功能
                    if ({{ (Auth::check() && Auth::user()->configs->get(\App\Enums\UserConfigKey::IsAutoClearPreview)) ? 1 : 0 }}) {
                        delete queue[data.$preview.data('id')];
                        data.$preview.remove();
                    } else {
                        setStatus(data, UPLOAD_SUCCESS)
                        data.$preview.attr('uploaded', true);
                    }

                    // 追加链接
                    let links = response.data.links;
                    for (let key in links) {
                        $('#links [data-tab="' + key + '"]').append('<p class="whitespace-nowrap select-all mt-1 bg-gray-50 hover:bg-gray-200 text-gray-600 rounded px-2 py-1 cursor-pointer overflow-scroll scrollbar-none">' + links[key].toString() + '</p>')
                    }
                    $links.show();
                    utils.setCapacityProgress(response.data.size);
                } else {
                    setStatus(data, UPLOAD_ERROR, "上传失败, " + response.message);
                    // 重新显示上传按钮
                    data.$preview.find('[data-operate="upload"]').show();
                }
            },
            fail: (e, data) => {
                if (data.errorThrown !== 'abort') {
                    // 重新显示上传按钮
                    data.$preview.find('[data-operate="upload"]').show();
                    if (data.jqXHR.status === 419) {
                        return setStatus(data, UPLOAD_ERROR, '令牌错误，请刷新网页重试');
                    }
                    return setStatus(data, UPLOAD_ERROR, '服务端异常，请稍后重试');
                }
            },
            // 等同于jq的complete
            always: (e, data) => {

            }
        });

        $(document).on('drop dragover', (e) => e.preventDefault());
        $previews.click((e) => e.stopPropagation());

        // 拖拽悬停时高亮拖放区
        let dndDepth = 0;
        $('#picker-dnd').on('dragenter', function (e) {
            e.preventDefault();
            dndDepth++;
            $(this).addClass('dnd-active');
        }).on('dragleave', function (e) {
            e.preventDefault();
            if (--dndDepth <= 0) { dndDepth = 0; $(this).removeClass('dnd-active'); }
        }).on('drop', function () {
            dndDepth = 0;
            $(this).removeClass('dnd-active');
        });

        $('#upload-all').click((e) => {
            // 队列中没有可上传的文件，选择则继续冒泡，选择文件
            if (Object.values(queue).filter((item) => item && ! item.started && item.status !== UPLOAD_SUCCESS).length) {
                e.stopPropagation();
                for (const key in queue) {
                    if (queue[key] && queue[key].status !== UPLOAD_SUCCESS) {
                        // 压缩中的项会被挂起，处理完自动提交（并发受限时不能直接跳过）
                        requestSubmit(key);
                    }
                }
            }
        });

        $previews.on('click', '[data-operate]', function (e) {
            e.stopPropagation();
            let $preview = $(this).closest('[data-id]');
            let method = $(this).data('operate');
            let id = $preview.data('id');
            if (method === 'remove') {
                queue[id].abort();
                delete queue[id];
                $preview.remove();
                refreshCompressProgress();
            }
            if (method === 'upload' && queue[id] && queue[id].status !== UPLOAD_SUCCESS) {
                requestSubmit(id);
            }
            if (method === 'preview') {
                let file = queue[id].files[0];
                if (excludes.indexOf(file.name.substring(file.name.lastIndexOf(".") + 1).toLowerCase()) === -1) {
                    let reader = new FileReader();
                    reader.readAsDataURL(file);
                    reader.onloadend = function (e) {
                        $('#preview-modal img').attr('src', e.target.result);
                        Alpine.store('modal').open('preview-modal')
                    }
                }
            }
        });

        $('#clear').click(function (e) {
            e.stopPropagation();
            queue = [];
            $previews.html('');
            refreshCompressProgress();
        });

        $('[data-tab-name]').click(function () {
            $(this).removeClass('active border-transparent')
                .addClass('active border-blue-500')
                .siblings()
                .removeClass('active border-blue-500')
                .addClass('border-transparent');
            $('[data-tab]').hide();
            $('[data-tab="' + $(this).data('tab-name') + '"]').show()
        });

        $('#clear-all').click(function () {
            $('[data-tab]').html('')
            $links.hide();
        });

        // 点击任意链接行自动复制（PC / 移动端一致；当前端加载后立即生效，包含已渲染与后续动态追加的行）
        $('#links').on('click', '[data-tab] p', function () {
            const $el = $(this);
            const text = $el.text().trim();
            if (! text) return;

            const copy = () => (navigator.clipboard && window.isSecureContext)
                ? navigator.clipboard.writeText(text)
                : new Promise((resolve, reject) => {
                    const ta = document.createElement('textarea');
                    ta.value = text;
                    ta.style.position = 'fixed';
                    ta.style.opacity = '0';
                    document.body.appendChild(ta);
                    ta.select();
                    try { document.execCommand('copy'); resolve(); }
                    catch (e) { reject(e); }
                    document.body.removeChild(ta);
                });

            copy().then(() => {
                const original = $el.text();
                $el.text('已复制');
                setTimeout(() => $el.text(original), 900);
            }).catch(() => toastr.warning('复制失败'));
        });

        // ---- 压缩开关初始化 ----
        // 每次页面加载都从「默认开启」开始：这里刻意不读取、也不写入任何持久化存储，
        // 用户在本次访问里关掉压缩只影响当前页面，刷新后即回到默认开启。
        (function initCompressSwitch() {
            if (compressDisabledReason) {
                compressEnabled = false;
                $('#compress-toggle').prop({ checked: false, disabled: true });
            } else {
                compressEnabled = true;
                $('#compress-toggle').prop('checked', true);
            }
            applyCompressUI();

            $('#compress-toggle').on('change', function () {
                if (compressDisabledReason) {
                    $(this).prop('checked', false);
                    return;
                }
                compressEnabled = $(this).prop('checked');
                applyCompressUI();
                if (! compressEnabled && ! compressTipShown) {
                    compressTipShown = true;
                    toastr.warning('已关闭压缩');
                }
                // 只重压尚未开始上传的项，正在传输中的不受影响
                recompressPending();
            });
        })();
    </script>
@endpush
