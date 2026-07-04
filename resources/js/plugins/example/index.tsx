import { registerRegion } from '@/components/shared/plugin-region';
import { Badge } from '@/components/ui/badge';

// Client half of src/Plugins/Example/ExamplePlugin.php: renders a badge in
// the workspace header of every entity workspace, demonstrating PluginRegion.
registerRegion('workspace-header-badge', () => (
    <Badge variant="outline">Example plugin</Badge>
));
