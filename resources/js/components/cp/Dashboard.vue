<template>
    <Head title="Sermon Formatter" />
    <div>
        <Header title="Sermon Formatter" icon="file-content-list" />

        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <Card inset>
                <div class="p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(34, 197, 94, 0.15);">
                            <Icon name="checkmark" class="w-5 h-5" style="color: #22c55e;" />
                        </div>
                        <div>
                            <Heading :level="2" size="xl">{{ stats.total_processed || 0 }}</Heading>
                            <Description>Processed</Description>
                        </div>
                    </div>
                </div>
            </Card>

            <Card inset>
                <div class="p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(239, 68, 68, 0.15);">
                            <Icon name="alert-warning-exclamation-mark" class="w-5 h-5" style="color: #ef4444;" />
                        </div>
                        <div>
                            <Heading :level="2" size="xl">{{ stats.total_failed || 0 }}</Heading>
                            <Description>Failed</Description>
                        </div>
                    </div>
                </div>
            </Card>

            <Card inset>
                <div class="p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(59, 130, 246, 0.15);">
                            <Icon name="time-clock" class="w-5 h-5" style="color: #3b82f6;" />
                        </div>
                        <div>
                            <Heading :level="2" size="xl">{{ stats.total_pending || 0 }}</Heading>
                            <Description>Pending</Description>
                        </div>
                    </div>
                </div>
            </Card>

            <Card inset>
                <div class="p-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0" style="background-color: rgba(168, 85, 247, 0.15);">
                            <Icon name="chart-monitoring-indicator" class="w-5 h-5" style="color: #a855f7;" />
                        </div>
                        <div>
                            <Heading :level="2" size="xl">{{ formatTokens(stats.total_tokens || 0) }}</Heading>
                            <Description>Total Tokens</Description>
                        </div>
                    </div>
                </div>
            </Card>
        </div>

        <!-- Info Cards -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <Panel heading="Processing Stats" subheading="Average performance">
                <Card>
                    <div class="p-4 space-y-3">
                        <div class="flex justify-between items-center">
                            <Description>Avg Processing Time</Description>
                            <Badge :text="formatAvgTime(stats.avg_processing_time)" color="blue" />
                        </div>
                        <div class="flex justify-between items-center">
                            <Description>Success Rate</Description>
                            <Badge :text="successRate" :color="successRateColor" />
                        </div>
                        <div class="flex justify-between items-center">
                            <Description>Total Tokens Used</Description>
                            <Badge :text="formatTokens(stats.total_tokens || 0)" color="gray" />
                        </div>
                    </div>
                </Card>
            </Panel>

            <Panel heading="Quick Actions" subheading="Common tasks">
                <Card>
                    <div class="p-4 space-y-3">
                        <Button
                            text="View Processing Logs"
                            variant="secondary"
                            href="/cp/sermon-formatter/logs"
                            class="w-full justify-center"
                        />
                        <Button
                            text="Edit Formatting Specs"
                            variant="secondary"
                            href="/cp/sermon-formatter/specs"
                            class="w-full justify-center"
                        />
                    </div>
                </Card>
            </Panel>
        </div>

        <!-- Recent Activity -->
        <Panel heading="Recent Activity" subheading="Last 10 processing jobs">
            <Card>
                <div v-if="loading" class="p-4 space-y-3">
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-full" />
                    <Skeleton class="h-8 w-3/4" />
                </div>

                <div v-else-if="stats.recent_logs?.length" class="p-4">
                    <Table>
                        <TableColumns>
                            <TableColumn>Status</TableColumn>
                            <TableColumn>File</TableColumn>
                            <TableColumn>Collection</TableColumn>
                            <TableColumn class="text-right">Tokens</TableColumn>
                            <TableColumn class="text-right">Time</TableColumn>
                            <TableColumn>When</TableColumn>
                        </TableColumns>
                        <TableRows>
                            <TableRow v-for="log in stats.recent_logs" :key="log.id">
                                <TableCell>
                                    <Badge
                                        :text="log.status"
                                        :color="getStatusBadgeColor(log.status)"
                                        size="sm"
                                    />
                                </TableCell>
                                <TableCell>
                                    <span class="truncate max-w-xs block">{{ log.file_name }}</span>
                                </TableCell>
                                <TableCell>{{ log.collection }}</TableCell>
                                <TableCell class="text-right">
                                    <span class="text-xs tabular-nums">{{ log.tokens ? formatTokens(log.tokens) : '--' }}</span>
                                </TableCell>
                                <TableCell class="text-right">
                                    <span class="text-xs tabular-nums">{{ formatTime(log.processing_time) }}</span>
                                </TableCell>
                                <TableCell>
                                    <span class="text-gray-500 text-xs">{{ log.created_at }}</span>
                                </TableCell>
                            </TableRow>
                        </TableRows>
                    </Table>
                </div>

                <ul v-else>
                    <EmptyStateItem
                        icon="file-content-list"
                        heading="No processing activity yet"
                        description="Recent processing jobs will appear here."
                    />
                </ul>
            </Card>
        </Panel>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, getCurrentInstance } from 'vue';
import { Head } from '@statamic/cms/inertia';
import {
    Badge,
    Button,
    Card,
    Description,
    EmptyStateItem,
    Header,
    Heading,
    Icon,
    Panel,
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

const stats = ref({});
const loading = ref(true);
let refreshInterval = null;

const successRate = computed(() => {
    const total = (stats.value.total_processed || 0) + (stats.value.total_failed || 0);
    if (total === 0) return '--';
    const rate = Math.round((stats.value.total_processed / total) * 100);
    return `${rate}%`;
});

const successRateColor = computed(() => {
    const total = (stats.value.total_processed || 0) + (stats.value.total_failed || 0);
    if (total === 0) return 'gray';
    const rate = (stats.value.total_processed / total) * 100;
    if (rate >= 90) return 'green';
    if (rate >= 70) return 'amber';
    return 'red';
});

async function loadStats() {
    loading.value = true;
    try {
        const response = await $axios.get('/cp/sermon-formatter/stats');
        stats.value = response.data;
    } catch (error) {
        console.error('Failed to load stats:', error);
    } finally {
        loading.value = false;
    }
}

function formatTokens(count) {
    if (count >= 1000000) return `${(count / 1000000).toFixed(1)}M`;
    if (count >= 1000) return `${(count / 1000).toFixed(1)}K`;
    return String(count);
}

function formatTime(value) {
    if (!value) return '--';
    const num = typeof value === 'number' ? value : parseFloat(value);
    if (isNaN(num)) return '--';
    return `${num.toFixed(1)}s`;
}

function formatAvgTime(value) {
    if (!value) return '0s';
    const num = typeof value === 'number' ? value : parseFloat(value);
    if (isNaN(num)) return '0s';
    return `${num.toFixed(1)}s`;
}

function getStatusBadgeColor(status) {
    return { completed: 'green', failed: 'red', processing: 'blue', pending: 'amber' }[status] || 'gray';
}

onMounted(() => {
    loadStats();
    refreshInterval = setInterval(() => loadStats(), 30000);
});

onBeforeUnmount(() => {
    if (refreshInterval) clearInterval(refreshInterval);
});
</script>
