@extends('adminLayout::index')

@section('pageContent')
    <x-page-title titleText=" {{ __('admin/media-migration.index_title') }}"></x-page-title>

    @livewire('admin.config.media-migration-tool')

    <script>
        document.addEventListener('alpine:init', () => {
            /**
             * 媒体迁移压缩包分片上传器。
             * 大文件切片（默认 8MB/片）逐片 POST 到后台专用路由，
             * 绕开 PHP 单请求上传体积限制；完成后触发 Livewire 事件刷新列表。
             */
            Alpine.data('mediaMigrationUploader', () => ({
                fileName: '',
                file: null,
                uploading: false,
                progress: 0,
                error: '',
                done: false,
                uploadedName: '',

                pick(event) {
                    const files = event.target.files;

                    if (! files.length) {
                        return;
                    }

                    this.file = files[0];
                    this.fileName = this.file.name;
                    this.error = '';
                    this.done = false;
                    this.progress = 0;
                },

                async start() {
                    if (! this.file || this.uploading) {
                        return;
                    }

                    this.uploading = true;
                    this.error = '';
                    this.progress = 0;

                    const CHUNK_SIZE = 8 * 1024 * 1024;
                    const totalChunks = Math.ceil(this.file.size / CHUNK_SIZE);
                    const uploadId = (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2)).replace(/[^a-zA-Z0-9]/g, '').slice(0, 32);
                    const url = @json(route('admin.config.media-migration.upload'));
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

                    try {
                        for (let index = 0; index < totalChunks; index++) {
                            const chunk = this.file.slice(index * CHUNK_SIZE, (index + 1) * CHUNK_SIZE);

                            const body = new FormData();
                            body.append('action', 'chunk');
                            body.append('upload_id', uploadId);
                            body.append('chunk_index', index);
                            body.append('total_chunks', totalChunks);
                            body.append('file', chunk);

                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'X-Requested-With': 'XMLHttpRequest',
                                },
                                body: body,
                            });

                            if (! response.ok) {
                                throw new Error(await this.readError(response));
                            }

                            this.progress = Math.round(((index + 1) / totalChunks) * 95);
                        }

                        const body = new FormData();
                        body.append('action', 'complete');
                        body.append('upload_id', uploadId);
                        body.append('filename', this.file.name);

                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrf,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: body,
                        });

                        const data = await response.json();

                        if (! response.ok || ! data.ok) {
                            throw new Error(data.message || 'Upload failed.');
                        }

                        this.progress = 100;
                        this.done = true;
                        this.uploadedName = data.name;

                        window.Livewire?.dispatch('media-archive-uploaded', { name: data.name });
                    } catch (error) {
                        this.error = error.message || 'Upload failed.';
                    } finally {
                        this.uploading = false;
                    }
                },

                async readError(response) {
                    try {
                        const data = await response.json();

                        return data.message || ('HTTP ' + response.status);
                    }
                    catch (error) {
                        return 'HTTP ' + response.status;
                    }
                },

                reset() {
                    this.file = null;
                    this.fileName = '';
                    this.uploading = false;
                    this.progress = 0;
                    this.error = '';
                    this.done = false;
                    this.uploadedName = '';
                },
            }));
        });
    </script>
@endsection
