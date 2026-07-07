import { DynamicSettingsForm } from '@/components/features/settings/dynamic-settings-form';
import type { DynamicSettingsFormProps } from '@/types/settings';

export default function DynamicSettingsPage(props: DynamicSettingsFormProps) {
    return (
        <div>
            <DynamicSettingsForm {...props} />
        </div>
    );
}
