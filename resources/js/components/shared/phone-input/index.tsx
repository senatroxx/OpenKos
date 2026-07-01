import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

const countries = [{ code: '+62', label: 'ID', prefix: '62' }];

export default function PhoneInput({
    name,
    defaultValue,
    placeholder,
}: {
    name: string;
    defaultValue?: string | null;
    placeholder?: string;
}) {
    const parsed = parseDefault(defaultValue);
    const [country, setCountry] = useState(parsed.country);
    const [number, setNumber] = useState(parsed.number);

    const full = country + number;

    return (
        <div className="flex gap-2">
            <Select value={country} onValueChange={setCountry}>
                <SelectTrigger className="w-20 shrink-0">
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    {countries.map((c) => (
                        <SelectItem key={c.code} value={c.prefix}>
                            {c.code}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            <input type="hidden" name={name} value={full} />
            <Input
                type="tel"
                value={number}
                onChange={(e) => setNumber(e.target.value.replace(/[^0-9]/g, ''))}
                placeholder={placeholder}
                className="flex-1"
            />
        </div>
    );
}

function parseDefault(value?: string | null): { country: string; number: string } {
    const raw = value?.replace(/[^0-9]/g, '') ?? '';

    if (raw.startsWith('62') && raw.length > 2) {
        return { country: '62', number: raw.slice(2) };
    }

    if (raw.startsWith('0')) {
        return { country: '62', number: raw.slice(1) };
    }

    return { country: '62', number: raw };
}
