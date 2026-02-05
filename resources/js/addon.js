// Sermon Formatter Addon - Statamic v6

// Fieldtype Component
import SermonSource from './components/fieldtypes/SermonSource.vue';

// Control Panel Components
import Dashboard from './components/cp/Dashboard.vue';
import SpecsEditor from './components/cp/SpecsEditor.vue';
import Logs from './components/cp/Logs.vue';

// Register Inertia pages for Control Panel navigation (Statamic v6)
Statamic.booting(() => {
    Statamic.$inertia.register('sermon-formatter::Dashboard', Dashboard);
    Statamic.$inertia.register('sermon-formatter::Specs', SpecsEditor);
    Statamic.$inertia.register('sermon-formatter::Logs', Logs);
});

// Register fieldtype
Statamic.$components.register('sermon_source-fieldtype', SermonSource);
