import { Head, usePage } from '@inertiajs/react';
import { ExternalLink, FileText, Info, Package } from 'lucide-react';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { license } from '@/routes/settings/about';

type Props = {
    build: {
        version: string;
        channel: string;
        commitSha: string | null;
    };
    product: {
        name: string;
        repositoryUrl: string;
        licenseName: string;
        copyright: string;
        logoUrl: string;
    };
};

export default function About({ build, product }: Props) {
    const { setting } = usePage<{ setting: { site_name: string } }>().props;
    const gitDescription = build.version.match(
        /^(.+)-(\d+)-g([0-9a-f]+)(-dirty)?$/i,
    );
    const displayVersion = gitDescription?.[1] ?? build.version;
    const buildLabel = gitDescription
        ? `${gitDescription[2]} commits after release · ${gitDescription[3]}${gitDescription[4] ? ' · Modified' : ''}`
        : build.commitSha?.slice(0, 12);

    return (
        <div className="space-y-6">
            <Head title="About" />

            <div>
                <h2 className="text-lg font-medium">About OpenKOS</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Information about the OpenKOS software running this
                    installation.
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-2 lg:items-start">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Package className="size-4" aria-hidden="true" />
                            OpenKOS software
                        </CardTitle>
                        <CardDescription>
                            Open-source property management software.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        <div className="flex items-center gap-4">
                            <img
                                src={product.logoUrl}
                                alt={`${product.name} logo`}
                                className="size-16 object-contain"
                            />
                            <div>
                                <p className="text-xl font-semibold">
                                    {product.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {product.copyright}
                                </p>
                            </div>
                        </div>

                        <dl className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Version
                                </dt>
                                <dd
                                    className="font-mono text-sm"
                                    title={build.version}
                                >
                                    {displayVersion}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm text-muted-foreground">
                                    Release channel
                                </dt>
                                <dd className="text-sm capitalize">
                                    {build.channel}
                                </dd>
                            </div>
                            {buildLabel && (
                                <div className="sm:col-span-2">
                                    <dt className="text-sm text-muted-foreground">
                                        Build
                                    </dt>
                                    <dd
                                        className="font-mono text-sm"
                                        title={build.version}
                                    >
                                        {buildLabel}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Info className="size-4" aria-hidden="true" />
                            Installation details
                        </CardTitle>
                        <CardDescription>
                            Safe information about this OpenKOS installation.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-5">
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Site name
                            </p>
                            <p className="font-medium">
                                {setting.site_name || product.name}
                            </p>
                        </div>

                        <div className="flex flex-col items-start gap-3">
                            <a
                                href={product.repositoryUrl}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                <ExternalLink
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                OpenKOS project repository
                            </a>
                            <a
                                href={license.url()}
                                target="_blank"
                                rel="noreferrer"
                                className="inline-flex items-center gap-2 text-sm font-medium text-primary underline-offset-4 hover:underline"
                            >
                                <FileText
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {product.licenseName}
                            </a>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}
