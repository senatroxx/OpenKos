import { registerRegion } from '@/components/shared/plugin-region';

// Client half of src/Plugins/Example/ExamplePlugin.php: content for the
// lease workspace tab it registers. Rendered only when the backend plugin
// is enabled (the tab doesn't exist otherwise).
registerRegion('workspace-tab-example', () => (
    <div className="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
        This content is rendered by the example plugin
        (resources/js/plugins/example).
    </div>
));
