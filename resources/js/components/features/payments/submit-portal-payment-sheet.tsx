import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { t } from '@/lib/i18n';
import type { Invoice } from '@/types';
import SubmitPortalPaymentForm from './submit-portal-payment-form';

export default function SubmitPortalPaymentSheet({
    invoice,
    open,
    onOpenChange,
}: {
    invoice: Invoice;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent className="sm:max-w-lg">
                <SheetHeader>
                    <SheetTitle>{t('Submit Payment')}</SheetTitle>
                    <SheetDescription>
                        {t(
                            'Your payment will be reviewed before it is applied to this invoice.',
                        )}
                    </SheetDescription>
                </SheetHeader>

                <SubmitPortalPaymentForm
                    key={invoice.id}
                    invoice={invoice}
                    onSuccess={() => onOpenChange(false)}
                    onCancel={() => onOpenChange(false)}
                />
            </SheetContent>
        </Sheet>
    );
}
