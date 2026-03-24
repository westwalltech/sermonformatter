<template>
    <Head title="Processing Logs" />
    <div>
        <Header title="Processing Logs" icon="file-content-list" />

        <!-- Filters -->
        <div class="flex gap-3 mb-4">
            <div class="flex-1">
                <Input
                    v-model="filters.search"
                    @update:model-value="debouncedLoad"
                    placeholder="Search by file name or entry ID..."
                />
            </div>
            <Select
                v-model="filters.status"
                @update:model-value="loadLogs"
                :options="statusOptions"
            />
            <Select
                v-model="filters.collection"
                @update:model-value="loadLogs"
                :options="collectionOptions"
            />
        </div>

        <!-- Logs Table -->
        <Card>
            <div v-if="loading" class="p-4 space-y-3">
                <Skeleton class="h-8 w-full" />
                <Skeleton class="h-8 w-full" />
                <Skeleton class="h-8 w-full" />
                <Skeleton class="h-8 w-3/4" />
            </div>

            <div v-else-if="logs.length" class="p-4">
                <Table class="table-fixed w-full">
                    <TableColumns>
                        <TableColumn class="w-24">Status</TableColumn>
                        <TableColumn>File</TableColumn>
                        <TableColumn class="w-28">Collection</TableColumn>
                        <TableColumn class="w-24 text-right">Tokens</TableColumn>
                        <TableColumn class="w-20 text-right">Time</TableColumn>
                        <TableColumn class="w-40">Date</TableColumn>
                        <TableColumn class="w-10"></TableColumn>
                    </TableColumns>
                    <TableRows>
                        <TableRow v-for="log in logs" :key="log.id">
                            <TableCell>
                                <Badge
                                    :text="log.status"
                                    :color="getStatusColor(log.status)"
                                    size="sm"
                                />
                            </TableCell>
                            <TableCell>
                                <span class="truncate block" :title="log.file_name">{{ log.file_name }}</span>
                            </TableCell>
                            <TableCell>{{ log.collection }}</TableCell>
                            <TableCell class="text-right">
                                <span v-if="log.input_tokens || log.output_tokens" class="text-xs tabular-nums">
                                    {{ formatTokens((log.input_tokens || 0) + (log.output_tokens || 0)) }}
                                </span>
                                <span v-else class="text-gray-400">--</span>
                            </TableCell>
                            <TableCell class="text-right">
                                <span v-if="log.processing_time" class="text-xs tabular-nums">
                                    {{ formatTime(log.processing_time) }}
                                </span>
                                <span v-else class="text-gray-400">--</span>
                            </TableCell>
                            <TableCell>
                                <span class="text-xs text-gray-500">{{ formatDate(log.created_at) }}</span>
                            </TableCell>
                            <TableCell class="text-right">
                                <!-- statamic-ui-auditor: row details via Dropdown instead of inline error column -->
                                <Dropdown v-if="log.error || log.model" align="end">
                                    <DropdownMenu>
                                        <DropdownLabel v-if="log.model" :text="`Model: ${log.model}`" />
                                        <DropdownSeparator v-if="log.model && log.error" />
                                        <DropdownLabel v-if="log.error" :text="log.error" class="text-red-600 dark:text-red-400 max-w-xs whitespace-normal" />
                                    </DropdownMenu>
                                </Dropdown>
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="mt-4 pt-4 border-t border-gray-200 dark:border-dark-600">
                    <Pagination
                        :resource-meta="pagination"
                        :show-per-page-selector="false"
                        :scroll-to-top="true"
                        @page-selected="goToPage"
                    />
                </div>
            </div>

            <ul v-else>
                <EmptyStateItem
                    icon="file-content-list"
                    heading="No processing logs found"
                    description="Logs will appear here after sermon documents are processed."
                />
            </ul>
        </Card>
    </div>
</template>

<script setup>
import { ref, computed, getCurrentInstance } from 'vue';
import { Head } from '@statamic/cms/inertia';
import {
    Badge,
    Card,
    Dropdown,
    DropdownLabel,
    DropdownMenu,
    DropdownSeparator,
    EmptyStateItem,
    Header,
    Input,
    Pagination,
    Select,
    Skeleton,
    Table,
    TableColumns,
    TableColumn,
    TableRows,
    TableRow,
    TableCell,
} from '@statamic/cms/ui';

const instance = getCurrentInstance();
const $axios = instance?.appContext?.config?.globalProperties?.$axios;

const logs = ref([]);
const loading = ref(true);
let searchTimeout = null;

const pagination = ref({
    current_page: 1,
    last_page: 1,
    from: 0,
    to: 0,
    total: 0,
});

const filters = ref({
    search: '',
    status: '',
    collection: '',
});

const statusOptions = computed(() => [
    { value: '', label: 'All Statuses' },
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'completed', label: 'Completed' },
    { value: 'failed', label: 'Failed' },
]);

const collectionOptions = computed(() => [
    { value: '', label: 'All Collections' },
    { value: 'messages', label: 'Messages' },
    { value: 'nss_messages', label: 'NSS Messages' },
]);

async function loadLogs(page = 1) {
    loading.value = true;
    try {
        const params = { page, per_page: 20 };
        if (filters.value.search) params.search = filters.value.search;
        if (filters.value.status) params.status = filters.value.status;
        if (filters.value.collection) params.collection = filters.value.collection;

        const response = await $axios.get('/cp/sermon-formatter/logs/data', { params });

        logs.value = response.data.data;
        pagination.value = {
            current_page: response.data.current_page,
            last_page: response.data.last_page,
            from: response.data.from || 0,
            to: response.data.to || 0,
            total: response.data.total || 0,
        };
    } catch (error) {
        console.error('Failed to load logs:', error);
    } finally {
        loading.value = false;
    }
}

function debouncedLoad() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => loadLogs(), 300);
}

function goToPage(page) {
    loadLogs(page);
}

function getStatusColor(status) {
    return { completed: 'green', failed: 'red', processing: 'blue', pending: 'amber' }[status] || 'gray';
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// statamic-ui-auditor: round raw float to 1 decimal place
function formatTime(seconds) {
    if (typeof seconds !== 'number') {
        seconds = parseFloat(seconds);
    }
    if (isNaN(seconds)) return '--';
    return `${seconds.toFixed(1)}s`;
}

function formatTokens(count) {
    if (count >= 1000000) return `${(count / 1000000).toFixed(1)}M`;
    if (count >= 1000) return `${(count / 1000).toFixed(1)}K`;
    return String(count);
}

// Load on mount
loadLogs();
</script>
