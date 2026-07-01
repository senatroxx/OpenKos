import { Form } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as editGeneral, update as updateGeneral } from '@/routes/settings/general';

export default function General({ settings }: { settings: { lease_id_prefix: string } }) {
    return (
        <div className="space-y-6">
            <div>
                <h2 className="text-lg font-medium">General settings</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Manage application-wide settings.
                </p>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Lease Reference</CardTitle>
                    <CardDescription>
                        Prefix used for auto-generated lease reference numbers.
                        Format: <code className="rounded bg-muted px-1.5 py-0.5 text-sm font-mono">{settings.lease_id_prefix}20260001</code>
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <Form
                        action={updateGeneral()}
                    >
                        {({ processing, errors }) => (
                            <div className="space-y-4">
                                <div className="grid gap-2 max-w-xs">
                                    <Label htmlFor="lease_id_prefix">Prefix</Label>
                                    <Input
                                        id="lease_id_prefix"
                                        name="lease_id_prefix"
                                        defaultValue={settings.lease_id_prefix}
                                        maxLength={10}
                                        className="font-mono uppercase"
                                        placeholder="LSX"
                                        required
                                    />
                                    {errors.lease_id_prefix && (
                                        <p className="text-sm text-red-600">{errors.lease_id_prefix}</p>
                                    )}
                                </div>
                                <Button disabled={processing}>
                                    Save
                                </Button>
                            </div>
                        )}
                    </Form>
                </CardContent>
            </Card>
        </div>
    );
}

General.layout = {
    breadcrumbs: [
        { title: 'General settings', href: editGeneral() },
    ],
};
