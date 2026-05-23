import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import properties from '@/routes/properties';
import type { Auth } from '@/types';

type Property = {
    id: number;
    name: string;
    slug: string;
    city: string | null;
    is_active: boolean;
    created_at: string;
};

type PageProps = {
    auth: Auth;
    properties: {
        data: Property[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
        };
    };
};

export default function Index({ properties: data }: PageProps) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Properties" />

            <div className="flex items-center justify-between">
                <Heading
                    title="Properties"
                    description="Manage your properties"
                />

                <Button asChild>
                    <Link href={properties.create()}>New Property</Link>
                </Button>
            </div>

            <div className="mt-6 space-y-4">
                {data.data.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center gap-4 py-12">
                            <p className="text-sm text-muted-foreground">
                                No properties yet.
                            </p>

                            <Button asChild>
                                <Link href={properties.create()}>
                                    Create your first property
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}

                {data.data.map((property) => (
                    <Card key={property.id}>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <div className="flex items-center gap-3">
                                <CardTitle className="text-base">
                                    {property.name}
                                </CardTitle>

                                {property.is_active ? (
                                    <Badge
                                        variant="default"
                                        className="bg-green-600"
                                    >
                                        Active
                                    </Badge>
                                ) : (
                                    <Badge variant="secondary">
                                        Archived
                                    </Badge>
                                )}
                            </div>

                            <Button variant="outline" size="sm" asChild>
                                <Link href={properties.edit(property)}>
                                    Edit
                                </Link>
                            </Button>
                        </CardHeader>

                        <CardContent>
                            {property.city && (
                                <p className="text-sm text-muted-foreground">
                                    {property.city}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        {
            title: 'Properties',
            href: properties.index(),
        },
    ],
};
