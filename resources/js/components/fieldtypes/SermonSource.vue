<template>
    <div class="sermon-source-fieldtype">
        <!-- Empty State: Upload File + Paste Text -->
        <div v-if="!value.status && !value.file_name && !analyzing && !analysis" class="space-y-3">
            <!-- File Upload -->
            <div
                class="border-2 border-dashed rounded-lg p-4 text-center transition-colors"
                :class="isDragging
                    ? 'border-blue-400 bg-blue-50 dark:bg-blue-900/20'
                    : 'border-gray-300 dark:border-dark-400 hover:border-gray-400 dark:hover:border-dark-300'"
                @dragover.prevent="isDragging = true"
                @dragleave="isDragging = false"
                @drop.prevent="onFileDrop"
            >
                <div class="flex items-center justify-center gap-3">
                    <Icon name="file-content-list" class="w-6 h-6 text-gray-400 dark:text-gray-500" />
                    <Description>
                        Drop a <strong>.docx</strong> or <strong>.rtf</strong> file here, or
                    </Description>
                    <input
                        ref="fileInput"
                        type="file"
                        :accept="acceptTypes"
                        class="hidden"
                        @change="onFileSelect"
                    />
                    <Button
                        @click="$refs.fileInput.click()"
                        variant="default"
                        size="sm"
                        text="Choose File"
                    />
                </div>
            </div>

            <!-- Paste Text -->
            <Description>Pasting text from your document is recommended for best results.</Description>
            <Textarea
                v-model="pastedText"
                :rows="6"
                placeholder="Or paste your sermon notes here..."
            />
            <div class="flex items-center gap-3">
                <Button
                    @click="analyzePastedText"
                    variant="primary"
                    size="sm"
                    text="Analyze"
                    :disabled="!pastedText?.trim()"
                />
                <Description v-if="pastedText?.trim()">
                    {{ pastedText.trim().length.toLocaleString() }} characters
                </Description>
            </div>
        </div>

        <!-- Analyzing State -->
        <Card v-else-if="analyzing" class="p-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(59, 130, 246, 0.15);">
                    <Icon name="loading" class="w-5 h-5 animate-spin" style="color: #3b82f6;" />
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ analyzingFileName }}</div>
                    <Description>Analyzing document...</Description>
                </div>
            </div>
        </Card>

        <!-- Analysis Confirmation -->
        <Card v-else-if="analysis" class="p-4">
            <div class="space-y-3">
                <!-- Slides export warning -->
                <Alert v-if="analysis.is_slides_export" variant="error">
                    This document contains {{ analysis.characters.toLocaleString() }} characters and appears to be a slides export, not sermon notes. Estimated cost: {{ analysis.estimated_cost_display }}. Please upload the notes document instead.
                </Alert>

                <!-- Large document warning -->
                <Alert v-else-if="analysis.is_large_document" variant="warning">
                    Large document: ~{{ formatTokens(analysis.estimated_tokens) }} tokens ({{ analysis.estimated_cost_display }}). This is larger than typical sermon notes. Proceed?
                </Alert>

                <!-- Normal document -->
                <div v-else class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(34, 197, 94, 0.15);">
                        <Icon name="checkmark" class="w-5 h-5" style="color: #22c55e;" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ analysis.file_name }}</div>
                        <Description>Ready to process: ~{{ formatTokens(analysis.estimated_tokens) }} tokens ({{ analysis.estimated_cost_display }})</Description>
                    </div>
                </div>

                <!-- File info for warning states -->
                <div v-if="analysis.is_slides_export || analysis.is_large_document" class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(107, 114, 128, 0.15);">
                        <Icon name="file-content-list" class="w-5 h-5" style="color: #6b7280;" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ analysis.file_name }}</div>
                        <Description>{{ analysis.characters.toLocaleString() }} characters &bull; ~{{ formatTokens(analysis.estimated_tokens) }} tokens</Description>
                    </div>
                </div>

                <!-- Action buttons -->
                <div class="flex gap-2 pt-1">
                    <Button
                        v-if="!analysis.exceeds_limit"
                        @click="confirmProcessing"
                        :loading="confirming"
                        variant="primary"
                        size="sm"
                        text="Process"
                    />
                    <Button
                        @click="cancelAnalysis"
                        variant="ghost"
                        size="sm"
                        text="Cancel"
                        :disabled="confirming"
                    />
                </div>
            </div>
        </Card>

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
        <Alert v-if="error" variant="error" class="mt-3">
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
    Textarea,
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
        Textarea,
    },

    data() {
        return {
            pastedText: '',
            isDragging: false,
            uploading: false,
            uploadProgress: 0,
            analyzing: false,
            analyzingFileName: '',
            analysis: null,
            confirming: false,
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
                this.analyzeFile(files[0]);
            }
        },

        onFileSelect(event) {
            const files = event.target.files;
            if (files.length > 0) {
                this.analyzeFile(files[0]);
            }
            event.target.value = '';
        },

        async analyzeFile(file) {
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
            this.analysis = null;
            this.analyzing = true;
            this.analyzingFileName = file.name;
            this.uploading = true;
            this.uploadProgress = 0;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await this.$axios.post(this.meta.analyzeUrl, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                    onUploadProgress: (event) => {
                        this.uploadProgress = Math.round((event.loaded / event.total) * 100);
                    },
                });

                if (response.data.success) {
                    this.analysis = response.data;
                } else {
                    this.error = response.data.message || 'Analysis failed.';
                }
            } catch (err) {
                const data = err.response?.data;
                if (data?.errors) {
                    this.error = Object.values(data.errors).flat().join(' ');
                } else {
                    this.error = data?.message || 'Analysis failed. Please try again.';
                }
            } finally {
                this.analyzing = false;
                this.uploading = false;
                this.uploadProgress = 0;
            }
        },

        async analyzePastedText() {
            const text = this.pastedText?.trim();
            if (!text) return;

            if (!this.entryId) {
                this.error = 'Please save the entry first before processing.';
                return;
            }

            this.error = null;
            this.analysis = null;
            this.analyzing = true;
            this.analyzingFileName = 'Pasted text';

            try {
                const response = await this.$axios.post(this.meta.analyzeTextUrl, { text });

                if (response.data.success) {
                    this.analysis = response.data;
                } else {
                    this.error = response.data.message || 'Analysis failed.';
                }
            } catch (err) {
                const data = err.response?.data;
                if (data?.errors) {
                    this.error = Object.values(data.errors).flat().join(' ');
                } else {
                    this.error = data?.message || 'Analysis failed. Please try again.';
                }
            } finally {
                this.analyzing = false;
            }
        },

        async confirmProcessing() {
            if (!this.analysis?.temp_file) return;

            this.confirming = true;
            this.error = null;

            try {
                const response = await this.$axios.post(this.meta.confirmUrl, {
                    entry_id: this.entryId,
                    collection: this.collection || '',
                    temp_file: this.analysis.temp_file,
                    target_field: this.config.target_field || 'notes',
                });

                if (response.data.success) {
                    this.$emit('update:value', {
                        status: 'pending',
                        file_name: this.analysis.file_name,
                        processed_at: null,
                        error: null,
                        log_id: response.data.log_id,
                    });

                    this.analysis = null;
                    this.pastedText = '';
                    this.$toast.success('Document queued for processing.');
                    this.startPolling();
                } else {
                    this.error = response.data.message || 'Failed to queue processing.';
                }
            } catch (err) {
                const data = err.response?.data;
                if (data?.errors) {
                    this.error = Object.values(data.errors).flat().join(' ');
                } else {
                    this.error = data?.message || 'Failed to confirm. Please try again.';
                }
            } finally {
                this.confirming = false;
            }
        },

        async cancelAnalysis() {
            if (this.analysis?.temp_file) {
                try {
                    await this.$axios.post(this.meta.cleanupUrl, {
                        temp_file: this.analysis.temp_file,
                    });
                } catch {
                    // Best-effort cleanup
                }
            }
            this.analysis = null;
            this.pastedText = '';
        },

        formatTokens(count) {
            if (count >= 1000000) return `${(count / 1000000).toFixed(1)}M`;
            if (count >= 1000) return `${(count / 1000).toFixed(1)}K`;
            return String(count);
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

                if (response.data.status && response.data.status !== this.value?.status) {
                    this.$emit('update:value', {
                        ...this.value,
                        status: response.data.status,
                        error: response.data.error,
                        processed_at: response.data.updated_at,
                    });

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
