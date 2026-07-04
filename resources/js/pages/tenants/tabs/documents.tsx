import { FileDown } from 'lucide-react';
import { formatDate } from '@/lib/formatters';
import type { TenantDocument } from '@/types';

export default function DocumentsTab({ docs }: { docs: TenantDocument[] }) {
    if (docs.length === 0) {
        return <p className="text-sm text-muted-foreground">No documents.</p>;
    }

    return (
        <div className="space-y-2">
            {docs.map((doc) => (
                <div key={doc.id} className="flex items-center justify-between rounded-lg border p-3 text-sm">
                    <div className="min-w-0 flex-1 truncate">
                        <span className="font-medium capitalize">{doc.type}</span>
                        <span className="ml-2 text-xs text-muted-foreground">
                            {formatDate(doc.created_at)}
                        </span>
                    </div>
                    <a
                        href={doc.download_url}
                        download={doc.original_name}
                        className="inline-flex size-8 items-center justify-center rounded-md text-muted-foreground hover:bg-accent hover:text-foreground"
                    >
                        <FileDown className="size-4" />
                    </a>
                </div>
            ))}
        </div>
    );
}
