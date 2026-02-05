<template>
    <Head title="Formatting Specs" />
    <div>
        <Header title="Formatting Specs" icon="file-content-list">
            <template #actions>
                <Button
                    v-if="!isDefault"
                    @click="resetSpecs"
                    variant="ghost"
                    text="Reset to Default"
                    :loading="resetting"
                />
                <Button
                    @click="saveSpecs"
                    variant="primary"
                    text="Save"
                    :loading="saving"
                    :disabled="!hasChanges"
                />
            </template>
        </Header>

        <div class="space-y-6">
            <!-- Editor -->
            <Panel heading="System Prompt" subheading="Instructions sent to Claude for formatting sermon notes">
                <Card>
                    <div class="p-4">
                        <Textarea
                            v-model="content"
                            :rows="20"
                            class="font-mono text-sm"
                            placeholder="Enter formatting specifications..."
                        />
                    </div>
                </Card>
            </Panel>

            <!-- Test Panel -->
            <Panel heading="Test Formatting" subheading="Paste sample text to test the formatting specs">
                <Card>
                    <div class="p-4 space-y-4">
                        <div>
                            <Label>Sample Text</Label>
                            <Textarea
                                v-model="testInput"
                                :rows="8"
                                placeholder="Paste sample sermon notes here..."
                            />
                        </div>
                        <Button
                            @click="testFormat"
                            variant="primary"
                            :loading="testing"
                            :disabled="!testInput.trim()"
                            text="Test Format"
                        />

                        <div v-if="testResult" class="space-y-3">
                            <div class="border-t border-gray-200 dark:border-dark-600 pt-3">
                                <Label>Formatted Output (Markdown)</Label>
                                <div class="bg-gray-50 dark:bg-dark-700 rounded-lg p-4 text-sm font-mono overflow-auto max-h-96" style="white-space: pre-wrap;">{{ testResult.markdown }}</div>
                            </div>
                            <div class="flex gap-4 text-xs text-gray-500">
                                <span>Input tokens: {{ testResult.tokens?.input }}</span>
                                <span>Output tokens: {{ testResult.tokens?.output }}</span>
                                <span>Model: {{ testResult.model }}</span>
                            </div>
                        </div>

                        <Alert v-if="testError" variant="danger">
                            {{ testError }}
                        </Alert>
                    </div>
                </Card>
            </Panel>
        </div>
    </div>
</template>

<script>
import { Head } from '@statamic/cms/inertia';
import {
    Alert,
    Button,
    Card,
    Header,
    Label,
    Panel,
    Textarea,
} from '@statamic/cms/ui';

export default {
    components: {
        Head,
        Alert,
        Button,
        Card,
        Header,
        Label,
        Panel,
        Textarea,
    },

    data() {
        return {
            content: '',
            originalContent: '',
            isDefault: true,
            saving: false,
            resetting: false,
            testInput: '',
            testResult: null,
            testError: null,
            testing: false,
        };
    },

    computed: {
        hasChanges() {
            return this.content !== this.originalContent;
        },
    },

    mounted() {
        this.loadSpecs();
    },

    methods: {
        async loadSpecs() {
            try {
                const response = await this.$axios.get('/cp/sermon-formatter/specs/content');
                this.content = response.data.content;
                this.originalContent = response.data.content;
                this.isDefault = response.data.is_default;
            } catch (error) {
                console.error('Failed to load specs:', error);
                this.$toast.error('Failed to load formatting specs.');
            }
        },

        async saveSpecs() {
            this.saving = true;
            try {
                const response = await this.$axios.post('/cp/sermon-formatter/specs', {
                    content: this.content,
                });

                if (response.data.success) {
                    this.originalContent = this.content;
                    this.isDefault = false;
                    this.$toast.success(response.data.message);
                }
            } catch (error) {
                this.$toast.error('Failed to save specs.');
            } finally {
                this.saving = false;
            }
        },

        async resetSpecs() {
            this.resetting = true;
            try {
                const response = await this.$axios.post('/cp/sermon-formatter/specs', {
                    content: '',
                    reset: true,
                });

                if (response.data.success) {
                    this.content = response.data.content;
                    this.originalContent = response.data.content;
                    this.isDefault = true;
                    this.$toast.success(response.data.message);
                }
            } catch (error) {
                this.$toast.error('Failed to reset specs.');
            } finally {
                this.resetting = false;
            }
        },

        async testFormat() {
            this.testing = true;
            this.testResult = null;
            this.testError = null;

            try {
                const response = await this.$axios.post('/cp/sermon-formatter/test', {
                    text: this.testInput,
                });

                if (response.data.success) {
                    this.testResult = response.data;
                } else {
                    this.testError = response.data.message;
                }
            } catch (error) {
                console.error('Test format error:', error.response?.status, error.response?.data);
                const data = error.response?.data;
                if (data?.errors) {
                    this.testError = Object.values(data.errors).flat().join(', ');
                } else {
                    this.testError = data?.message || 'Test failed.';
                }
            } finally {
                this.testing = false;
            }
        },
    },
};
</script>
