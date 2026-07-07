import { Form } from '@inertiajs/react';
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
import type { DynamicSettingsFormProps } from '@/types/settings';

export function DynamicSettingsForm({
    page,
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
                                        <div className="flex items-center gap-2">
                                            <Switch
                                                id={def.key}
                                                name="value"
                                                defaultChecked={!!values[def.key]}
                                            />
                                            <Label htmlFor={def.key}>
                                                {def.label}
                                            </Label>
                                        </div>
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
