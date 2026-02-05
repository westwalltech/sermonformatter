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
                            <Badge :text="`${stats.avg_processing_time || 0}s`" color="blue" />
                        </div>
                        <div class="flex justify-between items-center">
                            <Description>Success Rate</Description>
                            <Badge
                                :text="successRate"
                                :color="successRateColor"
                            />
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
                <div v-if="loading" class="flex items-center justify-center py-8">
                    <Icon name="loading" class="animate-spin h-6 w-6 text-gray-400" />
                </div>

                <div v-else-if="stats.recent_logs?.length" class="p-4">
                    <Table>
                        <TableColumns>
                            <TableColumn>Status</TableColumn>
                            <TableColumn>File</TableColumn>
                            <TableColumn>Collection</TableColumn>
                            <TableColumn>Tokens</TableColumn>
                            <TableColumn>Time</TableColumn>
                            <TableColumn>When</TableColumn>
                        </TableColumns>
                        <TableRows>
                            <TableRow v-for="log in stats.recent_logs" :key="log.id">
                                <TableCell>
                                    <div
                                        class="w-8 h-8 rounded-lg flex items-center justify-center"
                                        :style="getStatusStyle(log.status)"
                                    >
                                        <Icon :name="getStatusIcon(log.status)" class="w-4 h-4" :style="{ color: getStatusColor(log.status) }" />
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <span class="truncate max-w-xs block">{{ log.file_name }}</span>
                                </TableCell>
                                <TableCell>{{ log.collection }}</TableCell>
                                <TableCell>{{ log.tokens ? formatTokens(log.tokens) : '—' }}</TableCell>
                                <TableCell>{{ log.processing_time || '—' }}</TableCell>
                                <TableCell>
                                    <span class="text-gray-500">{{ log.created_at }}</span>
                                </TableCell>
                            </TableRow>
                        </TableRows>
                    </Table>
                </div>

                <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                    <Icon name="file-content-list" class="w-10 h-10 text-gray-300 dark:text-gray-600" />
                    <Description class="mt-2">No processing activity yet</Description>
                </div>
            </Card>
        </Panel>
    </div>
</template>

<script>
import { Head } from '@statamic/cms/inertia';
import {
    Header,
    Heading,
    Description,
    Card,
    Panel,
    Button,
    Badge,
    Icon,
    Table,
    TableColumns,
    TableColumn,
    TableRows,
    TableRow,
    TableCell,
} from '@statamic/cms/ui';

export default {
    components: {
        Head,
        Header,
        Heading,
        Description,
        Card,
        Panel,
        Button,
        Badge,
        Icon,
        Table,
        TableColumns,
        TableColumn,
        TableRows,
        TableRow,
        TableCell,
    },

    data() {
        return {
            stats: {},
            loading: true,
        };
    },

    computed: {
        successRate() {
            const total = (this.stats.total_processed || 0) + (this.stats.total_failed || 0);
            if (total === 0) return '—';
            const rate = Math.round((this.stats.total_processed / total) * 100);
            return `${rate}%`;
        },

        successRateColor() {
            const total = (this.stats.total_processed || 0) + (this.stats.total_failed || 0);
            if (total === 0) return 'gray';
            const rate = (this.stats.total_processed / total) * 100;
            if (rate >= 90) return 'green';
            if (rate >= 70) return 'amber';
            return 'red';
        },
    },

    mounted() {
        this.loadStats();
        this.refreshInterval = setInterval(() => this.loadStats(), 30000);
    },

    beforeUnmount() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
    },

    methods: {
        async loadStats() {
            this.loading = true;
            try {
                const response = await this.$axios.get('/cp/sermon-formatter/stats');
                this.stats = response.data;
            } catch (error) {
                console.error('Failed to load stats:', error);
            } finally {
                this.loading = false;
            }
        },

        formatTokens(count) {
            if (count >= 1000000) return `${(count / 1000000).toFixed(1)}M`;
            if (count >= 1000) return `${(count / 1000).toFixed(1)}K`;
            return String(count);
        },

        getStatusIcon(status) {
            return {
                completed: 'checkmark',
                failed: 'alert-warning-exclamation-mark',
                processing: 'loading',
                pending: 'time-clock',
            }[status] || 'info';
        },

        getStatusColor(status) {
            return {
                completed: '#22c55e',
                failed: '#ef4444',
                processing: '#3b82f6',
                pending: '#eab308',
            }[status] || '#6b7280';
        },

        getStatusStyle(status) {
            const colors = {
                completed: 'rgba(34, 197, 94, 0.15)',
                failed: 'rgba(239, 68, 68, 0.15)',
                processing: 'rgba(59, 130, 246, 0.15)',
                pending: 'rgba(234, 179, 8, 0.15)',
            };
            return { backgroundColor: colors[status] || 'rgba(107, 114, 128, 0.15)' };
        },
    },
};
</script>
