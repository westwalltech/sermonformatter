<template>
    <div class="sermon-source-fieldtype">
        <!-- Empty State: Drop Zone -->
        <div
            v-if="!value.status && !value.file_name"
            class="border-2 border-dashed rounded-lg p-8 text-center transition-colors"
            :class="isDragging
                ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                : 'border-gray-300 dark:border-dark-400 hover:border-gray-400 dark:hover:border-dark-300'"
            @dragover.prevent="isDragging = true"
            @dragleave="isDragging = false"
            @drop.prevent="onFileDrop"
        >
            <Icon name="file-content-list" class="w-10 h-10 mx-auto mb-3 text-gray-400 dark:text-gray-500" />
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                Drop a <strong>.docx</strong> or <strong>.rtf</strong> file here
            </p>
            <p class="text-xs text-gray-400 dark:text-gray-500 mb-4">
                or click to browse (max {{ meta.maxFileSize }}MB)
            </p>
            <input
                ref="fileInput"
                type="file"
                :accept="acceptTypes"
                class="hidden"
                @change="onFileSelect"
            />
            <Button
                @click="$refs.fileInput.click()"
                variant="secondary"
                size="sm"
                text="Choose File"
            />
        </div>

        <!-- Pending State -->
        <Card v-else-if="currentStatus === 'pending'" class="p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(59, 130, 246, 0.15);">
                    <Icon name="time-clock" class="w-5 h-5" style="color: #3b82f6;" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ value.file_name }}</div>
                    <Description>Queued for processing...</Description>
                </div>
                <Badge text="Queued" color="blue" />
            </div>
        </Card>

        <!-- Processing State -->
        <Card v-else-if="currentStatus === 'processing'" class="p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(59, 130, 246, 0.15);">
                    <Icon name="loading" class="w-5 h-5 animate-spin" style="color: #3b82f6;" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ value.file_name }}</div>
                    <Description>Processing with AI...</Description>
                </div>
                <Badge text="Processing" color="blue" />
            </div>
        </Card>

        <!-- Completed State -->
        <Card v-else-if="currentStatus === 'completed'" class="p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(34, 197, 94, 0.15);">
                    <Icon name="checkmark" class="w-5 h-5" style="color: #22c55e;" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ value.file_name }}</div>
                    <Description>
                        Processed {{ statusInfo.processing_time ? `in ${statusInfo.processing_time}s` : '' }}
                        {{ statusInfo.tokens ? `• ${statusInfo.tokens} tokens` : '' }}
                    </Description>
                </div>
                <Badge text="Complete" color="green" />
                <Button
                    @click="reprocess"
                    :loading="reprocessing"
                    variant="ghost"
                    size="sm"
                    icon="live-preview"
                    icon-only
                    class="shrink-0"
                />
                <Button
                    @click="reset"
                    variant="ghost"
                    size="sm"
                    icon="x"
                    icon-only
                    class="shrink-0 text-gray-400 hover:text-red-500"
                />
            </div>
        </Card>

        <!-- Failed State -->
        <Card v-else-if="currentStatus === 'failed'" class="p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(239, 68, 68, 0.15);">
                    <Icon name="alert-warning-exclamation-mark" class="w-5 h-5" style="color: #ef4444;" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ value.file_name }}</div>
                    <Description class="text-red-600 dark:text-red-400">
                        {{ statusInfo.error || value.error || 'Processing failed' }}
                    </Description>
                </div>
                <Badge text="Failed" color="red" />
                <Button
                    @click="reprocess"
                    :loading="reprocessing"
                    variant="secondary"
                    size="sm"
                    text="Retry"
                />
                <Button
                    @click="reset"
                    variant="ghost"
                    size="sm"
                    icon="x"
                    icon-only
                    class="shrink-0 text-gray-400 hover:text-red-500"
                />
            </div>
        </Card>

        <!-- Reload Prompt -->
        <Alert v-if="showReload" variant="success" class="mt-3">
            <div class="flex items-center justify-between">
                <span>Notes field updated. Reload to see the formatted content.</span>
                <Button
                    @click="reloadPage"
                    variant="primary"
                    size="sm"
                    text="Reload Page"
                />
            </div>
        </Alert>

        <!-- Upload Progress -->
        <div v-if="uploading" class="mt-2">
            <div class="w-full bg-gray-200 dark:bg-dark-700 rounded-full h-1.5">
                <div
                    class="h-1.5 rounded-full bg-blue-500 transition-all"
                    :style="{ width: uploadProgress + '%' }"
                ></div>
            </div>
        </div>

        <!-- Error Message -->
        <Alert v-if="error" variant="danger" class="mt-3">
            {{ error }}
        </Alert>
    </div>
</template>

<script>
import { Fieldtype } from '@statamic/cms';
import {
    Alert,
    Badge,
    Button,
    Card,
    Description,
    Icon,
} from '@statamic/cms/ui';

export default {
    mixins: [Fieldtype],

    components: {
        Alert,
        Badge,
        Button,
        Card,
        Description,
        Icon,
    },

    data() {
        return {
            isDragging: false,
            uploading: false,
            uploadProgress: 0,
            reprocessing: false,
            error: null,
            pollInterval: null,
            statusInfo: {},
            showReload: false,
        };
    },

    computed: {
        currentStatus() {
            return this.statusInfo.status || this.value?.status || null;
        },

        acceptTypes() {
            return (this.meta.allowedExtensions || ['docx', 'rtf'])
                .map(ext => `.${ext}`)
                .join(',');
        },

        entryId() {
            return this.meta?.entryId || null;
        },

        collection() {
            return this.meta?.collection || null;
        },
    },

    mounted() {
        // Start polling if in a transient state
        if (this.value?.status === 'pending' || this.value?.status === 'processing') {
            this.startPolling();
        }
    },

    beforeUnmount() {
        this.stopPolling();
    },

    methods: {
        onFileDrop(event) {
            this.isDragging = false;
            const files = event.dataTransfer.files;
            if (files.length > 0) {
                this.uploadFile(files[0]);
            }
        },

        onFileSelect(event) {
            const files = event.target.files;
            if (files.length > 0) {
                this.uploadFile(files[0]);
            }
            // Reset input so same file can be selected again
            event.target.value = '';
        },

        async uploadFile(file) {
            // Validate extension
            const ext = file.name.split('.').pop().toLowerCase();
            const allowed = this.meta.allowedExtensions || ['docx', 'rtf'];
            if (!allowed.includes(ext)) {
                this.error = `Invalid file type. Allowed: ${allowed.join(', ')}`;
                return;
            }

            // Validate size
            const maxSize = (this.meta.maxFileSize || 10) * 1024 * 1024;
            if (file.size > maxSize) {
                this.error = `File too large. Maximum: ${this.meta.maxFileSize}MB`;
                return;
            }

            if (!this.entryId) {
                this.error = 'Please save the entry first before uploading a document.';
                return;
            }

            this.error = null;
            this.uploading = true;
            this.uploadProgress = 0;

            const formData = new FormData();
            formData.append('file', file);
            formData.append('entry_id', this.entryId);
            formData.append('collection', this.collection || '');
            formData.append('target_field', this.config.target_field || 'notes');

            try {
                const response = await this.$axios.post(this.meta.uploadUrl, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (event) => {
                        this.uploadProgress = Math.round((event.loaded / event.total) * 100);
                    },
                });

                if (response.data.success) {
                    this.$emit('update:value', {
                        status: 'pending',
                        file_name: file.name,
                        processed_at: null,
                        error: null,
                        log_id: response.data.log_id,
                    });

                    this.$toast.success('Document uploaded and queued for processing.');
                    this.startPolling();
                } else {
                    this.error = response.data.message || 'Upload failed.';
                }
            } catch (err) {
                this.error = err.response?.data?.message || 'Upload failed. Please try again.';
            } finally {
                this.uploading = false;
                this.uploadProgress = 0;
            }
        },

        async reprocess() {
            if (!this.entryId) return;

            this.reprocessing = true;
            this.error = null;

            try {
                const url = this.meta.reprocessUrl.replace('__ENTRY_ID__', this.entryId);
                const response = await this.$axios.post(url);

                if (response.data.success) {
                    this.$emit('update:value', {
                        ...this.value,
                        status: 'pending',
                        error: null,
                        log_id: response.data.log_id,
                    });

                    this.statusInfo = {};
                    this.$toast.success('Re-processing queued.');
                    this.startPolling();
                } else {
                    this.error = response.data.message;
                }
            } catch (err) {
                this.error = err.response?.data?.message || 'Failed to reprocess.';
            } finally {
                this.reprocessing = false;
            }
        },

        reloadPage() {
            window.location.reload();
        },

        reset() {
            this.$emit('update:value', {
                status: null,
                file_name: null,
                processed_at: null,
                error: null,
                log_id: null,
            });
            this.statusInfo = {};
            this.error = null;
        },

        startPolling() {
            this.stopPolling();

            this.pollInterval = setInterval(async () => {
                await this.checkStatus();
            }, 3000);

            // Also check immediately
            this.checkStatus();
        },

        stopPolling() {
            if (this.pollInterval) {
                clearInterval(this.pollInterval);
                this.pollInterval = null;
            }
        },

        async checkStatus() {
            if (!this.entryId) return;

            try {
                const url = this.meta.statusUrl.replace('__ENTRY_ID__', this.entryId);
                const response = await this.$axios.get(url);
                this.statusInfo = response.data;

                // Update value if status changed
                if (response.data.status && response.data.status !== this.value?.status) {
                    this.$emit('update:value', {
                        ...this.value,
                        status: response.data.status,
                        error: response.data.error,
                        processed_at: response.data.updated_at,
                    });

                    // Stop polling if terminal state
                    if (response.data.status === 'completed' || response.data.status === 'failed') {
                        this.stopPolling();

                        if (response.data.status === 'completed') {
                            this.$toast.success('Sermon notes formatted successfully!');
                            this.showReload = true;
                        } else if (response.data.status === 'failed') {
                            this.$toast.error('Sermon processing failed.');
                        }
                    }
                }
            } catch (err) {
                console.error('Status check failed:', err);
            }
        },
    },
};
</script>
