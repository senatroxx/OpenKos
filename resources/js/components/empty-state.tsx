import { PackageOpen } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

export default function EmptyState({
    message,
    createLabel,
    onCreate,
}: {
    message: string;
    createLabel: string;
    onCreate: () => void;
}) {
    return (
        <Card>
            <CardContent className="flex flex-col items-center gap-4 py-16">
                <PackageOpen className="size-12 text-muted-foreground" />
                <p className="text-sm text-muted-foreground">{message}</p>
                <Button onClick={onCreate}>{createLabel}</Button>
            </CardContent>
        </Card>
    );
}