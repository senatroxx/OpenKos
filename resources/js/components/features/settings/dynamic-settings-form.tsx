import { useForm } from '@inertiajs/react';
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
import type {
    DynamicSettingsFormProps,
    SettingDefinition,
} from '@/types/settings';

export function DynamicSettingsForm({
    definitions,
    values,
}: DynamicSettingsFormProps) {
    return (
        <div className="space-y-6">
            {definitions.map((def) => (
                <SettingCard
                    key={def.key}
                    def={def}
                    rawValue={values[def.key]}
                />
            ))}
        </div>
    );
}

function SettingCard({
    def,
    rawValue,
}: {
    def: SettingDefinition;
    rawValue: unknown;
}) {
    const { data, setData, transform, submit, processing, errors } = useForm<{
        key: string;
        value: boolean | string;
    }>({
        key: def.key,
        value:
            def.type === 'bool'
                ? !!rawValue
                : def.type === 'json'
                  ? rawValue != null
                      ? JSON.stringify(rawValue, null, 2)
                      : ''
                  : String(rawValue ?? ''),
    });

    function handleSubmit(e: React.FormEvent) {
        e.preventDefault();

        if (def.type === 'bool') {
            transform((d) => ({ ...d, value: d.value ? '1' : '0' }));
        }

        // ponytail: replace with Wayfinder import after `php artisan wayfinder:generate`
        submit('post', '/settings/values');
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>{def.label}</CardTitle>
                {def.type === 'encrypted' && (
                    <CardDescription>Stored encrypted at rest.</CardDescription>
                )}
                {def.type === 'json' && (
                    <CardDescription>Enter valid JSON.</CardDescription>
                )}
            </CardHeader>
            <CardContent>
                <form onSubmit={handleSubmit} className="space-y-4">
                    {def.type === 'bool' ? (
                        <div className="flex items-center gap-2">
                            <Switch
                                id={def.key}
                                checked={Boolean(data.value)}
                                onCheckedChange={(v) => setData('value', v)}
                            />
                            <Label htmlFor={def.key}>{def.label}</Label>
                        </div>
                    ) : def.type === 'json' ? (
                        <div className="grid gap-2">
                            <Label htmlFor={def.key}>{def.label}</Label>
                            <Textarea
                                id={def.key}
                                value={String(data.value)}
                                onChange={(e) =>
                                    setData('value', e.target.value)
                                }
                                rows={4}
                            />
                            {errors.value && (
                                <p className="text-sm text-red-600">
                                    {errors.value}
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            <Label htmlFor={def.key}>{def.label}</Label>
                            <Input
                                id={def.key}
                                type={
                                    def.type === 'encrypted'
                                        ? 'password'
                                        : def.type === 'int'
                                          ? 'number'
                                          : 'text'
                                }
                                value={String(data.value)}
                                onChange={(e) =>
                                    setData('value', e.target.value)
                                }
                                placeholder={String(def.default ?? '')}
                            />
                            {errors.value && (
                                <p className="text-sm text-red-600">
                                    {errors.value}
                                </p>
                            )}
                        </div>
                    )}
                    <Button disabled={processing}>Save</Button>
                </form>
            </CardContent>
        </Card>
    );
}
