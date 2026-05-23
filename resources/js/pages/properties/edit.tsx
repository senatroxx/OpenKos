import { Form, Head, usePage } from '@inertiajs/react';
import PropertyController from '@/actions/App/Http/Controllers/PropertyController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

import properties from '@/routes/properties';
import type { Auth } from '@/types';

type Property = {
    id: number;
    name: string;
    address: string | null;
    city: string | null;
    province: string | null;
    postal_code: string | null;
    phone: string | null;
    email: string | null;
    is_active: boolean;
};

type PageProps = {
    auth: Auth;
    property: Property;
};

export default function Edit() {
    const { property } = usePage<PageProps>().props;

    return (
        <>
            <Head title={`Edit ${property.name}`} />

            <div className="flex items-center justify-between">
                <Heading
                    title={property.name}
                    description="Edit property details"
                />

                <Form
                    {...PropertyController.destroy.form(property)}
                    onSubmit={() =>
                        confirm(
                            'Are you sure you want to archive this property?',
                        )
                    }
                >
                    <Button variant="destructive" type="submit">
                        Archive
                    </Button>
                </Form>
            </div>

            <div className="mt-6 max-w-2xl">
                <Form
                    {...PropertyController.update.form(property)}
                    className="space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    defaultValue={property.name}
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="address">Address</Label>
                                <textarea
                                    id="address"
                                    name="address"
                                    defaultValue={property.address ?? ''}
                                    className="flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                <div className="grid gap-2">
                                    <Label htmlFor="city">City</Label>
                                    <Input
                                        id="city"
                                        name="city"
                                        defaultValue={property.city ?? ''}
                                    />
                                    <InputError message={errors.city} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="province">
                                        Province
                                    </Label>
                                    <Input
                                        id="province"
                                        name="province"
                                        defaultValue={
                                            property.province ?? ''
                                        }
                                    />
                                    <InputError message={errors.province} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="postal_code">
                                        Postal Code
                                    </Label>
                                    <Input
                                        id="postal_code"
                                        name="postal_code"
                                        defaultValue={
                                            property.postal_code ?? ''
                                        }
                                    />
                                    <InputError
                                        message={errors.postal_code}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        name="phone"
                                        type="tel"
                                        defaultValue={property.phone ?? ''}
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        defaultValue={property.email ?? ''}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <Button disabled={processing}>Save</Button>

                                <Button
                                    variant="outline"
                                    asChild
                                    disabled={processing}
                                >
                                    <a href={properties.index()}>
                                        Cancel
                                    </a>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

Edit.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: properties.index(),
        },
        {
            title: 'Edit Property',
            href: properties.edit({ id: 0 }),
        },
    ],
};
