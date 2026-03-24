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

<script setup>
import { ref, computed, getCurrentInstance } from 'vue';
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

const instance = getCurrentInstance();
const $axios = instance?.appContext?.config?.globalProperties?.$axios;
const $toast = instance?.appContext?.config?.globalProperties?.$toast;

const content = ref('');
const originalContent = ref('');
const isDefault = ref(true);
const saving = ref(false);
const resetting = ref(false);
const testInput = ref('');
const testResult = ref(null);
const testError = ref(null);
const testing = ref(false);

const hasChanges = computed(() => content.value !== originalContent.value);

async function loadSpecs() {
    try {
        const response = await $axios.get('/cp/sermon-formatter/specs/content');
        content.value = response.data.content;
        originalContent.value = response.data.content;
        isDefault.value = response.data.is_default;
    } catch (error) {
        console.error('Failed to load specs:', error);
        $toast.error('Failed to load formatting specs.');
    }
}

async function saveSpecs() {
    saving.value = true;
    try {
        const response = await $axios.post('/cp/sermon-formatter/specs', {
            content: content.value,
        });

        if (response.data.success) {
            originalContent.value = content.value;
            isDefault.value = false;
            $toast.success(response.data.message);
        }
    } catch (error) {
        $toast.error('Failed to save specs.');
    } finally {
        saving.value = false;
    }
}

async function resetSpecs() {
    resetting.value = true;
    try {
        const response = await $axios.post('/cp/sermon-formatter/specs', {
            content: '',
            reset: true,
        });

        if (response.data.success) {
            content.value = response.data.content;
            originalContent.value = response.data.content;
            isDefault.value = true;
            $toast.success(response.data.message);
        }
    } catch (error) {
        $toast.error('Failed to reset specs.');
    } finally {
        resetting.value = false;
    }
}

async function testFormat() {
    testing.value = true;
    testResult.value = null;
    testError.value = null;

    try {
        const response = await $axios.post('/cp/sermon-formatter/test', {
            text: testInput.value,
        });

        if (response.data.success) {
            testResult.value = response.data;
        } else {
            testError.value = response.data.message;
        }
    } catch (error) {
        console.error('Test format error:', error.response?.status, error.response?.data);
        const data = error.response?.data;
        if (data?.errors) {
            testError.value = Object.values(data.errors).flat().join(', ');
        } else {
            testError.value = data?.message || 'Test failed.';
        }
    } finally {
        testing.value = false;
    }
}

// Load on mount
loadSpecs();
</script>
