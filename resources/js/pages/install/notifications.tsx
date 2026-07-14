import { Form, Head } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import { useState } from 'react';
import { InstallStepper } from '@/components/install/stepper';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { configureNotifications } from '@/routes/install';

type Props = {
    steps: Record<string, boolean | null>;
    whatsappDrivers: { name: string; label: string }[];
};

export default function InstallNotifications({
    steps,
    whatsappDrivers,
}: Props) {
    const [waDriver, setWaDriver] = useState('log');

    return (
        <>
            <Head title="Notifications" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Notifications</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Configure email and WhatsApp (optional)
                        </p>
                        <p className="text-xs text-muted-foreground">
                            You can skip this and configure it later in Settings.
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <Form
                            {...configureNotifications.form()}
                            className="flex flex-col gap-6 divide-y divide-border"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Collapsible className="space-y-4">
                                        <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2 text-sm font-medium [&[data-state=open]>svg]:rotate-180">
                                            SMTP (Optional)
                                            <ChevronDown className="size-4 transition-transform" />
                                        </CollapsibleTrigger>
                                        <CollapsibleContent className="space-y-4 pb-4">
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="mail_host">
                                                        Host
                                                    </Label>
                                                    <Input
                                                        id="mail_host"
                                                        name="mail_host"
                                                        placeholder="smtp.example.com"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.mail_host
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="mail_port">
                                                        Port
                                                    </Label>
                                                    <Input
                                                        id="mail_port"
                                                        name="mail_port"
                                                        placeholder="587"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.mail_port
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="mail_username">
                                                        Username
                                                    </Label>
                                                    <Input
                                                        id="mail_username"
                                                        name="mail_username"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.mail_username
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="mail_password">
                                                        Password
                                                    </Label>
                                                    <Input
                                                        id="mail_password"
                                                        name="mail_password"
                                                        type="password"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.mail_password
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="mail_encryption">
                                                        Encryption
                                                    </Label>
                                                    <Select name="mail_encryption">
                                                        <SelectTrigger className="w-full">
                                                            <SelectValue placeholder="None" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="null">
                                                                None
                                                            </SelectItem>
                                                            <SelectItem value="tls">
                                                                TLS
                                                            </SelectItem>
                                                            <SelectItem value="ssl">
                                                                SSL
                                                            </SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                    <InputError
                                                        message={
                                                            errors.mail_encryption
                                                        }
                                                    />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="mail_from_address">
                                                        From Address
                                                    </Label>
                                                    <Input
                                                        id="mail_from_address"
                                                        name="mail_from_address"
                                                        type="email"
                                                        placeholder="noreply@example.com"
                                                    />
                                                    <InputError
                                                        message={
                                                            errors.mail_from_address
                                                        }
                                                    />
                                                </div>
                                            </div>
                                            <div className="grid gap-2">
                                                <Label htmlFor="mail_from_name">
                                                    From Name
                                                </Label>
                                                <Input
                                                    id="mail_from_name"
                                                    name="mail_from_name"
                                                    placeholder="OpenKOS"
                                                />
                                                <InputError
                                                    message={
                                                        errors.mail_from_name
                                                    }
                                                />
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>

                                    <Collapsible className="space-y-4">
                                        <CollapsibleTrigger className="flex w-full cursor-pointer items-center justify-between gap-2 text-sm font-medium [&[data-state=open]>svg]:rotate-180">
                                            WhatsApp
                                            <ChevronDown className="size-4 transition-transform" />
                                        </CollapsibleTrigger>
                                        <CollapsibleContent className="space-y-4 pb-4">
                                            <div className="grid gap-2">
                                                <Label>Driver</Label>
                                                <input
                                                    type="hidden"
                                                    name="whatsapp_driver"
                                                    value={waDriver}
                                                />
                                                <Select
                                                    value={waDriver}
                                                    onValueChange={setWaDriver}
                                                >
                                                    <SelectTrigger className="w-full">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {whatsappDrivers.map(
                                                            (d) => (
                                                                <SelectItem
                                                                    key={d.name}
                                                                    value={
                                                                        d.name
                                                                    }
                                                                >
                                                                    {d.label}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    message={
                                                        errors.whatsapp_driver
                                                    }
                                                />
                                            </div>
                                        </CollapsibleContent>
                                    </Collapsible>

                                    <Button
                                        type="submit"
                                        className="w-full"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Continue
                                    </Button>
                                </>
                            )}
                        </Form>
                    </div>
                </div>
            </div>
        </>
    );
}
