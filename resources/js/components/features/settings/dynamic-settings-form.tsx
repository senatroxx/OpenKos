import { useForm } from '@inertiajs/react';
import { upsert } from '@/actions/App/Http/Controllers/Settings/SettingValuesController';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import type { DynamicSettingsFormProps, SettingDefinition } from '@/types/settings';

function SettingField({ def, value }: { def: SettingDefinition; value: unknown }) {
    const normalizedValue = def.type === 'bool'
        ? (value === '1' || value === true ? '1' : '0')
        : def.type === 'json' && typeof value === 'object' && value !== null
            ? JSON.stringify(value)
            : value ?? def.default ?? '';

    const { data, setData, processing, errors, submit } = useForm({
        key: def.key,
        value: normalizedValue,
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();
        submit(upsert());
    }

    return (
        <form onSubmit={handleSubmit} className="space-y-4">
            {def.type === 'bool' ? (
                <div className="flex items-center gap-2">
                    <Switch id={def.key} checked={data.value === '1'} onCheckedChange={(v) => setData('value', v ? '1' : '0')} />
                    <Label htmlFor={def.key}>{def.label}</Label>
                </div>
            ) : def.type === 'json' ? (
                <div className="grid gap-2">
                    <Label htmlFor={def.key}>{def.label}</Label>
                    <Textarea
                        id={def.key}
                        value={data.value as string}
                        onChange={e => setData('value', e.target.value)}
                        rows={4}
                    />
                    {errors.value && (
                        <p className="text-sm text-red-600">{errors.value}</p>
                    )}
                </div>
            ) : (
                <div className="grid gap-2">
                    <Label htmlFor={def.key}>{def.label}</Label>
                    <Input
                        id={def.key}
                        type={def.type === 'encrypted' ? 'password' : def.type === 'int' ? 'number' : 'text'}
                        value={data.value as string | number}
                        onChange={e => setData('value', e.target.value)}
                        placeholder={String(def.default ?? '')}
                    />
                    {errors.value && (
                        <p className="text-sm text-red-600">{errors.value}</p>
                    )}
                </div>
            )}
            <Button disabled={processing}>Save</Button>
        </form>
    );
}

export function DynamicSettingsForm({
    definitions,
    values,
}: DynamicSettingsFormProps) {
    return (
        <div className="space-y-6">
            {definitions.map((def) => (
                <Card key={def.key}>
                    <CardHeader>
                        <CardTitle>{def.label}</CardTitle>
                        {def.type === 'encrypted' && (
                            <CardDescription>
                                Stored encrypted at rest.
                            </CardDescription>
                        )}
                        {def.type === 'json' && (
                            <CardDescription>
                                Enter valid JSON.
                            </CardDescription>
                        )}
                    </CardHeader>
                    <CardContent>
                        <SettingField def={def} value={values[def.key]} />
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
