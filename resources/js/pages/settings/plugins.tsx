import { Head, router, useForm, useHttp } from '@inertiajs/react';
import { useRef, useState } from 'react';
import InputError from '@/components/shared/input-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { t } from '@/lib/i18n';
import {
    destroy,
    disable,
    enable,
    index,
    install,
} from '@/routes/settings/plugins';
import {
    index as marketplaceIndex,
    install as marketplaceInstall,
    update as marketplaceUpdate,
} from '@/routes/settings/plugins/marketplace';
import { cleanup as cleanupRecovery } from '@/routes/settings/plugins/recovery';
import type {
    MarketplaceCatalog,
    MarketplacePlugin,
    MarketplaceUpdate,
    PlatformPlugin,
} from '@/types/platform';

type Props = {
    plugins: PlatformPlugin[];
    error: string | null;
    max_upload_bytes: number;
};

type RemoveConfirmation = {
    plugin: PlatformPlugin;
    force: boolean;
};

type MarketplaceSectionProps = {
    data: MarketplaceCatalog | null;
    error: string | null;
    processing: boolean;
    processingPlugin: string | null;
    search: string;
    onSearchChange: (value: string) => void;
    onSearch: (event: React.FormEvent<HTMLFormElement>) => void;
    onRetry: () => void;
    onPageChange: (page: number) => void;
    onAction: (pluginId: string, version: string, update: boolean) => void;
};

const statusVariants: Record<
    PlatformPlugin['status'],
    'default' | 'secondary' | 'destructive' | 'outline'
> = {
    enabled: 'default',
    disabled: 'secondary',
    legacy: 'outline',
    incompatible: 'destructive',
    broken: 'destructive',
    conflict: 'destructive',
    missing_package: 'destructive',
    pending_recovery: 'outline',
    unrecoverable_recovery: 'destructive',
    load_failed: 'destructive',
    orphaned_state: 'destructive',
    orphaned_runtime_artifact: 'outline',
};

function formatBytes(bytes: number): string {
    return `${Math.max(1, Math.round(bytes / 1024 / 1024))} MB`;
}

function safeExternalUrl(url: string | null): string | null {
    if (url === null) {
        return null;
    }

    try {
        const parsed = new URL(url);

        return parsed.protocol === 'http:' || parsed.protocol === 'https:'
            ? url
            : null;
    } catch {
        return null;
    }
}

function routeParts(id: string): { vendor: string; package: string } {
    const [vendor, packageName] = id.split('/');

    return { vendor, package: packageName };
}

function statusLabel(status: PlatformPlugin['status']): string {
    return {
        enabled: t('Enabled'),
        disabled: t('Disabled'),
        legacy: t('Legacy'),
        incompatible: t('Incompatible'),
        broken: t('Broken'),
        conflict: t('Conflict'),
        missing_package: t('Missing package'),
        pending_recovery: t('Pending recovery'),
        unrecoverable_recovery: t('Unrecoverable recovery'),
        load_failed: t('Load failed'),
        orphaned_state: t('Orphaned state'),
        orphaned_runtime_artifact: t('Orphaned runtime artifact'),
    }[status];
}

function pluginKey(plugin: PlatformPlugin): string {
    return plugin.managed_id ?? plugin.id;
}

function MarketplaceSection({
    data,
    error,
    processing,
    processingPlugin,
    search,
    onSearchChange,
    onSearch,
    onRetry,
    onPageChange,
    onAction,
}: MarketplaceSectionProps) {
    function actionFor(plugin: MarketplacePlugin): {
        label: string;
        version: string;
        update: boolean;
    } | null {
        const compatible = plugin.latest_compatible_version;

        if (
            compatible === null ||
            plugin.installed_source === 'composer' ||
            plugin.installed_source === 'explicit'
        ) {
            return null;
        }

        if (plugin.installed_version === null) {
            return {
                label: t('Install'),
                version: compatible.version,
                update: false,
            };
        }

        const update = data?.updates.find(
            (item) => item.plugin_id === plugin.id,
        );

        if (plugin.installed_source === 'marketplace' && update !== undefined) {
            return {
                label: t('Update'),
                version: update.available_version.version,
                update: true,
            };
        }

        if (plugin.installed_source === 'manual') {
            return {
                label: t('Install from Marketplace'),
                version: compatible.version,
                update: false,
            };
        }

        return null;
    }

    return (
        <div className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>{t('Find a plugin')}</CardTitle>
                    <CardDescription>
                        {t(
                            'Browse compatible runtime plugins from the OpenKOS Marketplace.',
                        )}
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form
                        className="flex flex-col gap-3 sm:flex-row"
                        onSubmit={onSearch}
                    >
                        <Label className="sr-only" htmlFor="marketplace_search">
                            {t('Search marketplace')}
                        </Label>
                        <Input
                            id="marketplace_search"
                            value={search}
                            placeholder={t('Search by plugin name or ID')}
                            onChange={(event) =>
                                onSearchChange(event.target.value)
                            }
                            disabled={processing}
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                        >
                            {processing ? t('Searching...') : t('Search')}
                        </Button>
                    </form>
                </CardContent>
            </Card>

            {(error || data?.error) && (
                <Alert variant="destructive">
                    <AlertTitle>{t('Marketplace unavailable')}</AlertTitle>
                    <AlertDescription>
                        {error ?? data?.error}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mt-3"
                            onClick={onRetry}
                            disabled={processing}
                        >
                            {t('Retry')}
                        </Button>
                    </AlertDescription>
                </Alert>
            )}

            {processing && data === null ? (
                <div
                    className="grid gap-4 lg:grid-cols-2"
                    aria-label={t('Loading marketplace')}
                >
                    {[1, 2].map((item) => (
                        <div
                            key={item}
                            className="h-56 animate-pulse rounded-lg border bg-muted/40"
                        />
                    ))}
                </div>
            ) : data !== null &&
              data.error === null &&
              data.plugins.length === 0 ? (
                <Alert>
                    <AlertTitle>{t('No marketplace plugins found')}</AlertTitle>
                    <AlertDescription>
                        {t('Try a different search term.')}
                    </AlertDescription>
                </Alert>
            ) : data !== null ? (
                <>
                    {data.updates.length > 0 && (
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Updates available')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'Updates are always started explicitly by an owner.',
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                {data.updates.map(
                                    (update: MarketplaceUpdate) => {
                                        const key = `${update.plugin_id}@${update.available_version.version}`;

                                        return (
                                            <div
                                                key={key}
                                                className="flex flex-wrap items-center justify-between gap-3 rounded-md border p-3"
                                            >
                                                <div>
                                                    <p className="font-medium">
                                                        {update.name}
                                                    </p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {t(
                                                            ':installed → :available',
                                                            {
                                                                installed:
                                                                    update.installed_version,
                                                                available:
                                                                    update
                                                                        .available_version
                                                                        .version,
                                                            },
                                                        )}
                                                    </p>
                                                </div>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={
                                                        processingPlugin === key
                                                    }
                                                    onClick={() =>
                                                        onAction(
                                                            update.plugin_id,
                                                            update
                                                                .available_version
                                                                .version,
                                                            true,
                                                        )
                                                    }
                                                >
                                                    {processingPlugin === key
                                                        ? t('Updating...')
                                                        : t('Update')}
                                                </Button>
                                            </div>
                                        );
                                    },
                                )}
                            </CardContent>
                        </Card>
                    )}

                    <div className="grid gap-4 lg:grid-cols-2">
                        {data.plugins.map((plugin) => {
                            const compatible = plugin.latest_compatible_version;
                            const action = actionFor(plugin);
                            const projectUrl =
                                safeExternalUrl(plugin.repository_url) ??
                                safeExternalUrl(plugin.homepage_url);
                            const publisherUrl = safeExternalUrl(
                                plugin.publisher?.url ?? null,
                            );
                            const actionKey =
                                action === null
                                    ? null
                                    : `${plugin.id}@${action.version}`;
                            const isProcessing =
                                actionKey !== null &&
                                processingPlugin === actionKey;

                            return (
                                <Card key={plugin.id}>
                                    <CardHeader>
                                        <div className="flex flex-wrap items-start justify-between gap-3">
                                            <div>
                                                <CardTitle>
                                                    {plugin.name}
                                                </CardTitle>
                                                <CardDescription className="mt-1 font-mono">
                                                    {plugin.id}
                                                </CardDescription>
                                            </div>
                                            {plugin.compatible && (
                                                <Badge variant="secondary">
                                                    {t('Compatible')}
                                                </Badge>
                                            )}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <p className="text-sm text-muted-foreground">
                                            {plugin.summary ??
                                                plugin.description ??
                                                t('No description provided.')}
                                        </p>

                                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('Installed')}
                                                </dt>
                                                <dd>
                                                    {plugin.installed_version ??
                                                        t('Not installed')}
                                                </dd>
                                            </div>
                                            <div>
                                                <dt className="text-muted-foreground">
                                                    {t('Latest compatible')}
                                                </dt>
                                                <dd>
                                                    {compatible?.version ??
                                                        t('None')}
                                                </dd>
                                            </div>
                                            {compatible?.artifact.size !==
                                                undefined && (
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        {t('Artifact size')}
                                                    </dt>
                                                    <dd>
                                                        {formatBytes(
                                                            compatible.artifact
                                                                .size,
                                                        )}
                                                    </dd>
                                                </div>
                                            )}
                                            {plugin.publisher?.name && (
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        {t('Publisher')}
                                                    </dt>
                                                    <dd className="break-words">
                                                        {publisherUrl ? (
                                                            <a
                                                                className="text-primary underline-offset-4 hover:underline"
                                                                href={
                                                                    publisherUrl
                                                                }
                                                                target="_blank"
                                                                rel="noreferrer"
                                                            >
                                                                {
                                                                    plugin
                                                                        .publisher
                                                                        .name
                                                                }
                                                            </a>
                                                        ) : (
                                                            plugin.publisher
                                                                .name
                                                        )}
                                                    </dd>
                                                </div>
                                            )}
                                            {plugin.installed_source && (
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        {t('Installed source')}
                                                    </dt>
                                                    <dd>
                                                        {plugin.installed_source ===
                                                        'marketplace'
                                                            ? t('Marketplace')
                                                            : plugin.installed_source ===
                                                                'manual'
                                                              ? t('Manual ZIP')
                                                              : plugin.installed_source ===
                                                                  'composer'
                                                                ? t('Composer')
                                                                : t(
                                                                      'Built-in / explicit',
                                                                  )}
                                                    </dd>
                                                </div>
                                            )}
                                        </dl>

                                        {!plugin.compatible && (
                                            <Alert>
                                                <AlertDescription>
                                                    {t(
                                                        'No compatible release is available for this installation.',
                                                    )}
                                                </AlertDescription>
                                            </Alert>
                                        )}

                                        <div className="flex flex-wrap items-center gap-2">
                                            {action !== null ? (
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    disabled={isProcessing}
                                                    onClick={() =>
                                                        onAction(
                                                            plugin.id,
                                                            action.version,
                                                            action.update,
                                                        )
                                                    }
                                                >
                                                    {isProcessing
                                                        ? action.update
                                                            ? t('Updating...')
                                                            : t('Installing...')
                                                        : action.label}
                                                </Button>
                                            ) : plugin.installed_source ===
                                                  'composer' ||
                                              plugin.installed_source ===
                                                  'explicit' ? (
                                                <p className="text-sm text-muted-foreground">
                                                    {t(
                                                        'Managed by the application and cannot be changed here.',
                                                    )}
                                                </p>
                                            ) : plugin.installed_version !==
                                                  null &&
                                              compatible !== null ? (
                                                <p className="text-sm text-muted-foreground">
                                                    {t('Installed')}
                                                </p>
                                            ) : null}

                                            {projectUrl && (
                                                <a
                                                    className="text-sm text-primary underline-offset-4 hover:underline"
                                                    href={projectUrl}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    {t('View project')}
                                                </a>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            );
                        })}
                    </div>
                    {data.pagination.total_page > 1 && (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-muted-foreground">
                                {t('Page :current of :total', {
                                    current: data.pagination.current_page,
                                    total: data.pagination.total_page,
                                })}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        processing ||
                                        data.pagination.current_page <= 1
                                    }
                                    onClick={() =>
                                        onPageChange(
                                            data.pagination.current_page - 1,
                                        )
                                    }
                                >
                                    {t('Previous')}
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        processing ||
                                        data.pagination.current_page >=
                                            data.pagination.total_page
                                    }
                                    onClick={() =>
                                        onPageChange(
                                            data.pagination.current_page + 1,
                                        )
                                    }
                                >
                                    {t('Next')}
                                </Button>
                            </div>
                        </div>
                    )}
                </>
            ) : null}
        </div>
    );
}

export default function Plugins({
    plugins,
    error,
    max_upload_bytes: maxUploadBytes,
}: Props) {
    const fileInput = useRef<HTMLInputElement>(null);
    const [uploadConfirmationOpen, setUploadConfirmationOpen] = useState(false);
    const [removeConfirmation, setRemoveConfirmation] =
        useState<RemoveConfirmation | null>(null);
    const [processingPlugin, setProcessingPlugin] = useState<string | null>(
        null,
    );
    const [actionError, setActionError] = useState<string | null>(null);
    const [activeSection, setActiveSection] = useState<
        'installed' | 'marketplace'
    >('installed');
    const [marketplaceData, setMarketplaceData] =
        useState<MarketplaceCatalog | null>(null);
    const [marketplaceError, setMarketplaceError] = useState<string | null>(
        null,
    );
    const [marketplaceSearch, setMarketplaceSearch] = useState('');
    const [marketplacePage, setMarketplacePage] = useState(1);
    const marketplaceRequest = useHttp<
        Record<string, never>,
        MarketplaceCatalog
    >({});
    const uploadForm = useForm<{ file: File | null }>({ file: null });

    function loadMarketplace(
        search = marketplaceSearch,
        page = marketplacePage,
    ) {
        setMarketplaceError(null);
        marketplaceRequest.get(
            marketplaceIndex({
                query: { q: search || undefined, page, limit: 20 },
            }).url,
            {
                onSuccess: (response) => {
                    setMarketplaceData(response);
                    setMarketplaceError(response.error);
                    setMarketplacePage(response.pagination.current_page);
                },
                onError: () =>
                    setMarketplaceError(
                        t('The marketplace could not be loaded.'),
                    ),
                onHttpException: () => {
                    setMarketplaceError(
                        t('The marketplace could not be loaded.'),
                    );
                },
                onNetworkError: () => {
                    setMarketplaceError(
                        t(
                            'The marketplace is unavailable. Installed plugins and manual ZIP management are still available.',
                        ),
                    );
                },
            },
        );
    }

    function openMarketplace() {
        setActiveSection('marketplace');

        if (marketplaceData === null && !marketplaceRequest.processing) {
            loadMarketplace();
        }
    }

    function runMarketplaceAction(
        pluginId: string,
        version: string,
        update: boolean,
    ) {
        if (
            !window.confirm(
                t(
                    update
                        ? 'Update plugin to :version?'
                        : 'Install plugin version :version?',
                    {
                        version,
                    },
                ),
            )
        ) {
            return;
        }

        setActionError(null);
        setProcessingPlugin(`${pluginId}@${version}`);
        router.post(
            (update ? marketplaceUpdate() : marketplaceInstall()).url,
            { plugin_id: pluginId, version },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setMarketplaceData(null);
                    setMarketplaceError(null);
                    setActiveSection('installed');
                },
                onError: (errors) => setActionError(errors.marketplace ?? null),
                onFinish: () => setProcessingPlugin(null),
            },
        );
    }

    function requestUpload(event: React.FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (uploadForm.data.file) {
            setUploadConfirmationOpen(true);
        }
    }

    function confirmUpload() {
        setUploadConfirmationOpen(false);
        uploadForm.submit(install(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                setMarketplaceData(null);
                setMarketplaceError(null);

                if (fileInput.current) {
                    fileInput.current.value = '';
                }
            },
        });
    }

    function runAction(
        plugin: PlatformPlugin,
        action: 'enable' | 'disable',
        force = false,
    ) {
        if (plugin.managed_id === null) {
            return;
        }

        const parts = routeParts(plugin.managed_id);
        const route = action === 'enable' ? enable(parts) : disable(parts);

        setActionError(null);
        setProcessingPlugin(pluginKey(plugin));
        router.post(route.url, force ? { force: true } : {}, {
            preserveScroll: true,
            onError: (errors) => setActionError(errors.plugin ?? null),
            onFinish: () => setProcessingPlugin(null),
        });
    }

    function confirmRemove() {
        if (!removeConfirmation) {
            return;
        }

        const { plugin, force } = removeConfirmation;

        if (plugin.managed_id === null) {
            return;
        }

        setRemoveConfirmation(null);
        setActionError(null);
        setProcessingPlugin(pluginKey(plugin));
        router.delete(destroy(routeParts(plugin.managed_id)).url, {
            data: force ? { force: true } : {},
            preserveScroll: true,
            onSuccess: () => {
                setMarketplaceData(null);
                setMarketplaceError(null);
            },
            onError: (errors) => setActionError(errors.plugin ?? null),
            onFinish: () => setProcessingPlugin(null),
        });
    }

    function cleanupMetadata(plugin: PlatformPlugin) {
        const key = pluginKey(plugin);
        const data = plugin.cleanup_key
            ? { cleanup_key: plugin.cleanup_key }
            : plugin.managed_id
              ? { recovery_id: plugin.managed_id }
              : {};

        setActionError(null);
        setProcessingPlugin(key);
        router.delete(cleanupRecovery().url, {
            data,
            preserveScroll: true,
            onError: (errors) => setActionError(errors.plugin ?? null),
            onFinish: () => setProcessingPlugin(null),
        });
    }

    return (
        <>
            <Head title={t('Plugins')} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-lg font-medium">{t('Plugins')}</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {t(
                            'Inspect and manage trusted OpenKOS plugin packages.',
                        )}
                    </p>
                </div>

                <Alert>
                    <AlertTitle>{t('Only install trusted plugins')}</AlertTitle>
                    <AlertDescription>
                        {t(
                            'Uploaded plugins execute server-side code. ZIP files are validated before activation, but only install artifacts from a trusted source.',
                        )}
                    </AlertDescription>
                </Alert>

                {error && (
                    <Alert variant="destructive">
                        <AlertTitle>
                            {t('Runtime plugins unavailable')}
                        </AlertTitle>
                        <AlertDescription>{error}</AlertDescription>
                    </Alert>
                )}

                {actionError && (
                    <Alert variant="destructive">
                        <AlertTitle>{t('Plugin action failed')}</AlertTitle>
                        <AlertDescription>{actionError}</AlertDescription>
                    </Alert>
                )}

                <div className="flex gap-2 border-b" role="tablist">
                    <Button
                        type="button"
                        variant={
                            activeSection === 'installed' ? 'default' : 'ghost'
                        }
                        role="tab"
                        aria-selected={activeSection === 'installed'}
                        onClick={() => setActiveSection('installed')}
                    >
                        {t('Installed plugins')}
                    </Button>
                    <Button
                        type="button"
                        variant={
                            activeSection === 'marketplace'
                                ? 'default'
                                : 'ghost'
                        }
                        role="tab"
                        aria-selected={activeSection === 'marketplace'}
                        onClick={openMarketplace}
                    >
                        {t('Marketplace')}
                    </Button>
                </div>

                {activeSection === 'marketplace' ? (
                    <MarketplaceSection
                        data={marketplaceData}
                        error={marketplaceError}
                        processing={marketplaceRequest.processing}
                        processingPlugin={processingPlugin}
                        search={marketplaceSearch}
                        onSearchChange={setMarketplaceSearch}
                        onSearch={(event) => {
                            event.preventDefault();
                            setMarketplacePage(1);
                            loadMarketplace(marketplaceSearch, 1);
                        }}
                        onRetry={() => loadMarketplace()}
                        onPageChange={(page) => {
                            setMarketplacePage(page);
                            loadMarketplace(marketplaceSearch, page);
                        }}
                        onAction={runMarketplaceAction}
                    />
                ) : (
                    <>
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('Install plugin')}</CardTitle>
                                <CardDescription>
                                    {t(
                                        'Upload a prepared runtime plugin ZIP, up to :size.',
                                        {
                                            size: formatBytes(maxUploadBytes),
                                        },
                                    )}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form
                                    onSubmit={requestUpload}
                                    className="grid gap-3"
                                >
                                    <Label htmlFor="plugin_file">
                                        {t('Plugin ZIP')}
                                    </Label>
                                    <Input
                                        ref={fileInput}
                                        id="plugin_file"
                                        type="file"
                                        accept=".zip,application/zip"
                                        onChange={(event) =>
                                            uploadForm.setData(
                                                'file',
                                                event.target.files?.[0] ?? null,
                                            )
                                        }
                                        disabled={uploadForm.processing}
                                    />
                                    <InputError
                                        message={uploadForm.errors.file}
                                    />
                                    {uploadForm.progress && (
                                        <p className="text-sm text-muted-foreground">
                                            {t('Uploading')}{' '}
                                            {uploadForm.progress.percentage}%
                                        </p>
                                    )}
                                    <div>
                                        <Button
                                            type="submit"
                                            disabled={
                                                !uploadForm.data.file ||
                                                uploadForm.processing
                                            }
                                        >
                                            {uploadForm.processing
                                                ? t('Installing...')
                                                : t('Upload and install')}
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>

                        {plugins.length === 0 ? (
                            <Alert>
                                <AlertTitle>{t('No plugins found')}</AlertTitle>
                                <AlertDescription>
                                    {t(
                                        'Upload a prepared runtime plugin ZIP to get started.',
                                    )}
                                </AlertDescription>
                            </Alert>
                        ) : (
                            <div className="grid gap-4 lg:grid-cols-2">
                                {plugins.map((plugin) => (
                                    <Card
                                        key={`${plugin.source}:${pluginKey(plugin)}`}
                                    >
                                        <CardHeader>
                                            <div className="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <CardTitle>
                                                        {plugin.name}
                                                    </CardTitle>
                                                    <CardDescription className="mt-1 font-mono">
                                                        {plugin.managed_id ??
                                                            plugin.id}
                                                    </CardDescription>
                                                </div>
                                                <Badge
                                                    variant={
                                                        statusVariants[
                                                            plugin.status
                                                        ]
                                                    }
                                                >
                                                    {statusLabel(plugin.status)}
                                                </Badge>
                                            </div>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <p className="text-sm text-muted-foreground">
                                                {plugin.description ||
                                                    t(
                                                        'No description provided.',
                                                    )}
                                            </p>

                                            <dl className="grid gap-3 text-sm sm:grid-cols-2">
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        {t('Source')}
                                                    </dt>
                                                    <dd>
                                                        {plugin.source ===
                                                        'runtime'
                                                            ? t('Runtime ZIP')
                                                            : plugin.source ===
                                                                'explicit'
                                                              ? t(
                                                                    'Built-in / explicit',
                                                                )
                                                              : t(
                                                                    'Composer-installed',
                                                                )}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt className="text-muted-foreground">
                                                        {t('Version')}
                                                    </dt>
                                                    <dd>
                                                        {plugin.version ??
                                                            t('Unknown')}
                                                    </dd>
                                                </div>
                                                {plugin.declared_id &&
                                                    plugin.declared_id !==
                                                        plugin.managed_id && (
                                                        <div className="sm:col-span-2">
                                                            <dt className="text-muted-foreground">
                                                                {t(
                                                                    'Declared plugin ID',
                                                                )}
                                                            </dt>
                                                            <dd className="font-mono break-all">
                                                                {
                                                                    plugin.declared_id
                                                                }
                                                            </dd>
                                                        </div>
                                                    )}
                                                {plugin.core_version && (
                                                    <div>
                                                        <dt className="text-muted-foreground">
                                                            {t(
                                                                'OpenKOS requirement',
                                                            )}
                                                        </dt>
                                                        <dd className="font-mono">
                                                            {
                                                                plugin.core_version
                                                            }
                                                        </dd>
                                                    </div>
                                                )}
                                                {plugin.php && (
                                                    <div>
                                                        <dt className="text-muted-foreground">
                                                            {t(
                                                                'PHP requirement',
                                                            )}
                                                        </dt>
                                                        <dd className="font-mono">
                                                            {plugin.php}
                                                        </dd>
                                                    </div>
                                                )}
                                                {plugin.entry_class && (
                                                    <div className="sm:col-span-2">
                                                        <dt className="text-muted-foreground">
                                                            {t('Entry class')}
                                                        </dt>
                                                        <dd className="font-mono break-all">
                                                            {plugin.entry_class}
                                                        </dd>
                                                    </div>
                                                )}
                                            </dl>

                                            {plugin.dependencies.length > 0 && (
                                                <p className="text-xs text-muted-foreground">
                                                    {t('Dependencies')}:{' '}
                                                    {plugin.dependencies.join(
                                                        ', ',
                                                    )}
                                                </p>
                                            )}

                                            {plugin.error && (
                                                <Alert variant="destructive">
                                                    <AlertDescription>
                                                        {plugin.error}
                                                    </AlertDescription>
                                                </Alert>
                                            )}

                                            {plugin.can_force_recovery && (
                                                <Alert variant="destructive">
                                                    <AlertDescription>
                                                        {t(
                                                            'Recovery actions are available because this runtime package or its dependency graph is invalid. They may leave dependent plugins unavailable.',
                                                        )}
                                                    </AlertDescription>
                                                </Alert>
                                            )}

                                            {plugin.can_cleanup && (
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="destructive"
                                                        disabled={
                                                            processingPlugin ===
                                                            pluginKey(plugin)
                                                        }
                                                        onClick={() =>
                                                            cleanupMetadata(
                                                                plugin,
                                                            )
                                                        }
                                                    >
                                                        {t('Clean up metadata')}
                                                    </Button>
                                                </div>
                                            )}

                                            {(plugin.can_enable ||
                                                plugin.can_disable ||
                                                plugin.can_remove ||
                                                plugin.can_force_recovery) && (
                                                <div className="flex flex-wrap gap-2">
                                                    {plugin.can_enable && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            disabled={
                                                                processingPlugin ===
                                                                pluginKey(
                                                                    plugin,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                runAction(
                                                                    plugin,
                                                                    'enable',
                                                                )
                                                            }
                                                        >
                                                            {t('Enable')}
                                                        </Button>
                                                    )}
                                                    {plugin.can_disable && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            disabled={
                                                                processingPlugin ===
                                                                pluginKey(
                                                                    plugin,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                runAction(
                                                                    plugin,
                                                                    'disable',
                                                                )
                                                            }
                                                        >
                                                            {t('Disable')}
                                                        </Button>
                                                    )}
                                                    {plugin.can_force_recovery && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={
                                                                processingPlugin ===
                                                                pluginKey(
                                                                    plugin,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                runAction(
                                                                    plugin,
                                                                    'disable',
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            {t('Force disable')}
                                                        </Button>
                                                    )}
                                                    {plugin.can_remove && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={
                                                                processingPlugin ===
                                                                pluginKey(
                                                                    plugin,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                setRemoveConfirmation(
                                                                    {
                                                                        plugin,
                                                                        force: false,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {t('Remove')}
                                                        </Button>
                                                    )}
                                                    {plugin.can_force_recovery && (
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="destructive"
                                                            disabled={
                                                                processingPlugin ===
                                                                pluginKey(
                                                                    plugin,
                                                                )
                                                            }
                                                            onClick={() =>
                                                                setRemoveConfirmation(
                                                                    {
                                                                        plugin,
                                                                        force: true,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            {t('Force remove')}
                                                        </Button>
                                                    )}
                                                </div>
                                            )}
                                        </CardContent>
                                    </Card>
                                ))}
                            </div>
                        )}
                    </>
                )}
            </div>

            <Dialog
                open={uploadConfirmationOpen}
                onOpenChange={setUploadConfirmationOpen}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {t('Install trusted plugin?')}
                        </DialogTitle>
                        <DialogDescription>
                            {t(
                                'This ZIP will be validated and installed as server-side code. Because its plugin identity is not known until validation, it may replace an existing runtime plugin with the same validated ID.',
                            )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button type="button" onClick={confirmUpload}>
                            {t('Validate and install')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={removeConfirmation !== null}
                onOpenChange={(open) => !open && setRemoveConfirmation(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            {removeConfirmation?.force
                                ? t('Force remove runtime plugin?')
                                : t('Remove runtime plugin?')}
                        </DialogTitle>
                        <DialogDescription>
                            {removeConfirmation?.force
                                ? t(
                                      'This recovery action removes the managed package directory even when lifecycle metadata is corrupt. Plugin-owned database data will not be deleted. A restart is required for changes to take effect.',
                                  )
                                : t(
                                      'This removes only the runtime package and its activation state. Plugin-owned database data will not be deleted. A restart is required for changes to take effect.',
                                  )}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button type="button" variant="outline">
                                {t('Cancel')}
                            </Button>
                        </DialogClose>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={confirmRemove}
                        >
                            {removeConfirmation?.force
                                ? t('Force remove plugin')
                                : t('Remove plugin')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Plugins.layout = {
    breadcrumbs: [{ title: t('Plugins'), href: index().url }],
};
