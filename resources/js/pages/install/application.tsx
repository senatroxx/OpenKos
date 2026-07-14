import { Form, Head } from '@inertiajs/react';
import { Check, ChevronsUpDown } from 'lucide-react';
import { useState } from 'react';
import { InstallStepper } from '@/components/install/stepper';
import { InputError } from '@/components/shared';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import { configureApplication } from '@/routes/install';

type Props = {
    steps: Record<string, boolean | null>;
    timezones: string[];
};

export default function InstallApplication({ steps, timezones }: Props) {
    const [tzOpen, setTzOpen] = useState(false);
    const [tzValue, setTzValue] = useState('Asia/Jakarta');

    return (
        <>
            <Head title="Application Settings" />
            <div className="flex min-h-screen flex-col items-center justify-center bg-[#FDFDFC] p-6 dark:bg-[#0a0a0a]">
                <div className="w-full max-w-lg space-y-8">
                    <InstallStepper steps={steps} />
                    <div className="text-center">
                        <h1 className="text-2xl font-bold">Application Settings</h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Configure your application
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-8 text-card-foreground shadow-sm">
                        <Form
                            {...configureApplication.form()}
                            className="flex flex-col gap-6"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="app_url">Application URL</Label>
                                        <Input
                                            id="app_url"
                                            name="app_url"
                                            type="url"
                                            required
                                            defaultValue="http://localhost:8000"
                                            placeholder="http://localhost:8000"
                                        />
                                        <InputError message={errors.app_url} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="app_name">Application Name</Label>
                                        <Input
                                            id="app_name"
                                            name="app_name"
                                            required
                                            defaultValue="OpenKOS"
                                            placeholder="OpenKOS"
                                        />
                                        <InputError message={errors.app_name} />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label>Timezone</Label>
                                            <input type="hidden" name="timezone" value={tzValue} />
                                            <Popover open={tzOpen} onOpenChange={setTzOpen}>
                                                <PopoverTrigger asChild>
                                                    <Button
                                                        variant="outline"
                                                        role="combobox"
                                                        className="w-full justify-between"
                                                    >
                                                        {tzValue}
                                                        <ChevronsUpDown className="ml-2 size-4 shrink-0 opacity-50" />
                                                    </Button>
                                                </PopoverTrigger>
                                                <PopoverContent className="w-[var(--radix-popover-trigger-width)] p-0">
                                                    <Command>
                                                        <CommandInput placeholder="Search timezone..." />
                                                        <CommandList>
                                                            <CommandEmpty>No timezone found.</CommandEmpty>
                                                            <CommandGroup>
                                                                {timezones.map(tz => (
                                                                    <CommandItem
                                                                        key={tz}
                                                                        value={tz}
                                                                        onSelect={() => {
                                                                            setTzValue(tz);
                                                                            setTzOpen(false);
                                                                        }}
                                                                    >
                                                                        <Check
                                                                            className={cn(
                                                                                'mr-2 size-4',
                                                                                tzValue === tz ? 'opacity-100' : 'opacity-0',
                                                                            )}
                                                                        />
                                                                        {tz}
                                                                    </CommandItem>
                                                                ))}
                                                            </CommandGroup>
                                                        </CommandList>
                                                    </Command>
                                                </PopoverContent>
                                            </Popover>
                                            <InputError message={errors.timezone} />
                                        </div>
                                        <div className="grid gap-2">
                                            <Label htmlFor="locale">Locale</Label>
                                            <input type="hidden" name="locale" value="en" />
                                            <Input
                                                id="locale"
                                                name="locale_disabled"
                                                disabled
                                                defaultValue="en"
                                            />
                                            <InputError message={errors.locale} />
                                        </div>
                                    </div>
                                    <p className="-mt-2 text-xs text-muted-foreground">
                                        Internationalization is not yet implemented. Locale defaults to English.
                                    </p>

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
