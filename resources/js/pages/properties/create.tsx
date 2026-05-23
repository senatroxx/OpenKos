import { Form, Head } from '@inertiajs/react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import properties, { store } from '@/routes/properties';

export default function Create() {
    return (
        <>
            <Head title="New Property" />

            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <Heading
                    title="New Property"
                    description="Add a new property to manage"
                />

                <div className="max-w-2xl">
                    <Form
                        action={store.url()} method="post"
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
                                        placeholder="e.g. Kos Melati"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Address</Label>
                                    <textarea
                                        id="address"
                                        name="address"
                                        placeholder="Property address"
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
                                            placeholder="City"
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
                                            placeholder="Province"
                                        />
                                        <InputError
                                            message={errors.province}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="postal_code">
                                            Postal Code
                                        </Label>
                                        <Input
                                            id="postal_code"
                                            name="postal_code"
                                            placeholder="Postal code"
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
                                            placeholder="Phone number"
                                        />
                                        <InputError
                                            message={errors.phone}
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            placeholder="Email address"
                                        />
                                        <InputError
                                            message={errors.email}
                                        />
                                    </div>
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button disabled={processing}>
                                        Save
                                    </Button>

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
            </div>
        </>
    );
}

Create.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: properties.index(),
        },
        {
            title: 'New Property',
            href: properties.create(),
        },
    ],
};
