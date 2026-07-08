import { Form } from '@inertiajs/react';
import { useState } from 'react';
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
                        {/* ponytail: replace with Wayfinder import after `php artisan wayfinder:generate` */}
                        <Form
                            action={'/settings/values'}
                            method="post"
                        >
                            {({ processing, errors }) => (
                                <div className="space-y-4">
                                    <input type="hidden" name="key" value={def.key} />
                                    {def.type === 'bool' ? (
                                        <BoolField def={def} value={!!values[def.key]} />
                                    ) : def.type === 'json' ? (
                                        <div className="grid gap-2">
                                            <Label htmlFor={def.key}>
                                                {def.label}
                                            </Label>
                                            <Textarea
                                                id={def.key}
                                                name="value"
                                                defaultValue={
                                                    values[def.key] != null
                                                        ? JSON.stringify(
                                                              values[def.key],
                                                              null,
                                                              2,
                                                          )
                                                        : ''
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
                                            <Label htmlFor={def.key}>
                                                {def.label}
                                            </Label>
                                            <Input
                                                id={def.key}
                                                name="value"
                                                type={
                                                    def.type === 'encrypted'
                                                        ? 'password'
                                                        : def.type === 'int'
                                                          ? 'number'
                                                          : 'text'
                                                }
                                                defaultValue={
                                                    values[def.key] as string | number | undefined
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
                                    <Button disabled={processing}>
                                        Save
                                    </Button>
                                </div>
                            )}
                        </Form>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function BoolField({ def, value }: { def: SettingDefinition; value: boolean }) {
    const [checked, setChecked] = useState(value);

    return (
        <div className="flex items-center gap-2">
            <input type="hidden" name="value" value={checked ? '1' : '0'} />
            <Switch id={def.key} checked={checked} onCheckedChange={setChecked} />
            <Label htmlFor={def.key}>{def.label}</Label>
        </div>
    );
}
