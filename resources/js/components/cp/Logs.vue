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
            <div v-if="loading" class="flex items-center justify-center py-8">
                <Icon name="loading" class="animate-spin h-6 w-6 text-gray-400" />
            </div>

            <div v-else-if="logs.length" class="p-4">
                <Table>
                    <TableColumns>
                        <TableColumn>Status</TableColumn>
                        <TableColumn>File</TableColumn>
                        <TableColumn>Collection</TableColumn>
                        <TableColumn>Model</TableColumn>
                        <TableColumn>Tokens</TableColumn>
                        <TableColumn>Time</TableColumn>
                        <TableColumn>Date</TableColumn>
                        <TableColumn>Error</TableColumn>
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
                                <span class="truncate max-w-xs block">{{ log.file_name }}</span>
                            </TableCell>
                            <TableCell>{{ log.collection }}</TableCell>
                            <TableCell>
                                <span class="text-xs text-gray-500">{{ log.model || '—' }}</span>
                            </TableCell>
                            <TableCell>
                                <span v-if="log.input_tokens || log.output_tokens" class="text-xs">
                                    {{ (log.input_tokens || 0) + (log.output_tokens || 0) }}
                                </span>
                                <span v-else>—</span>
                            </TableCell>
                            <TableCell>
                                {{ log.processing_time ? `${log.processing_time}s` : '—' }}
                            </TableCell>
                            <TableCell>
                                <span class="text-xs text-gray-500">{{ formatDate(log.created_at) }}</span>
                            </TableCell>
                            <TableCell>
                                <span
                                    v-if="log.error"
                                    class="text-xs text-red-600 dark:text-red-400 truncate max-w-xs block cursor-help"
                                    :title="log.error"
                                >
                                    {{ log.error }}
                                </span>
                                <span v-else>—</span>
                            </TableCell>
                        </TableRow>
                    </TableRows>
                </Table>

                <!-- Pagination -->
                <div v-if="pagination.last_page > 1" class="flex items-center justify-between mt-4 pt-4 border-t border-gray-200 dark:border-dark-600">
                    <Description>
                        Showing {{ pagination.from }}–{{ pagination.to }} of {{ pagination.total }}
                    </Description>
                    <div class="flex gap-2">
                        <Button
                            @click="goToPage(pagination.current_page - 1)"
                            :disabled="pagination.current_page <= 1"
                            variant="ghost"
                            size="sm"
                            text="Previous"
                        />
                        <Button
                            @click="goToPage(pagination.current_page + 1)"
                            :disabled="pagination.current_page >= pagination.last_page"
                            variant="ghost"
                            size="sm"
                            text="Next"
                        />
                    </div>
                </div>
            </div>

            <div v-else class="flex flex-col items-center justify-center py-8 text-center">
                <Icon name="file-content-list" class="w-10 h-10 text-gray-300 dark:text-gray-600" />
                <Description class="mt-2">No processing logs found</Description>
            </div>
        </Card>
    </div>
</template>

<script>
import { Head } from '@statamic/cms/inertia';
import {
    Badge,
    Button,
    Card,
    Description,
    Header,
    Icon,
    Input,
    Select,
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
        Badge,
        Button,
        Card,
        Description,
        Header,
        Icon,
        Input,
        Select,
        Table,
        TableColumns,
        TableColumn,
        TableRows,
        TableRow,
        TableCell,
    },

    data() {
        return {
            logs: [],
            loading: true,
            searchTimeout: null,
            pagination: {
                current_page: 1,
                last_page: 1,
                from: 0,
                to: 0,
                total: 0,
            },
            filters: {
                search: '',
                status: '',
                collection: '',
            },
        };
    },

    computed: {
        statusOptions() {
            return [
                { value: '', label: 'All Statuses' },
                { value: 'pending', label: 'Pending' },
                { value: 'processing', label: 'Processing' },
                { value: 'completed', label: 'Completed' },
                { value: 'failed', label: 'Failed' },
            ];
        },

        collectionOptions() {
            return [
                { value: '', label: 'All Collections' },
                { value: 'messages', label: 'Messages' },
                { value: 'nss_messages', label: 'NSS Messages' },
            ];
        },
    },

    mounted() {
        this.loadLogs();
    },

    methods: {
        async loadLogs(page = 1) {
            this.loading = true;
            try {
                const params = {
                    page,
                    per_page: 20,
                };

                if (this.filters.search) params.search = this.filters.search;
                if (this.filters.status) params.status = this.filters.status;
                if (this.filters.collection) params.collection = this.filters.collection;

                const response = await this.$axios.get('/cp/sermon-formatter/logs/data', { params });

                this.logs = response.data.data;
                this.pagination = {
                    current_page: response.data.current_page,
                    last_page: response.data.last_page,
                    from: response.data.from || 0,
                    to: response.data.to || 0,
                    total: response.data.total || 0,
                };
            } catch (error) {
                console.error('Failed to load logs:', error);
            } finally {
                this.loading = false;
            }
        },

        debouncedLoad() {
            clearTimeout(this.searchTimeout);
            this.searchTimeout = setTimeout(() => {
                this.loadLogs();
            }, 300);
        },

        goToPage(page) {
            this.loadLogs(page);
        },

        getStatusColor(status) {
            return {
                completed: 'green',
                failed: 'red',
                processing: 'blue',
                pending: 'amber',
            }[status] || 'gray';
        },

        formatDate(dateString) {
            if (!dateString) return '';
            const date = new Date(dateString);
            return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        },
    },
};
</script>
