import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';

export default function ThemeToggleButton() {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const ThemeIcon = resolvedAppearance === 'dark' ? Sun : Moon;
    const label =
        resolvedAppearance === 'dark'
            ? 'Switch to light theme'
            : 'Switch to dark theme';

    return (
        <Button
            variant="ghost"
            size="icon"
            aria-label={label}
            title={label}
            onClick={() =>
                updateAppearance(
                    resolvedAppearance === 'dark' ? 'light' : 'dark',
                )
            }
        >
            <ThemeIcon className="size-4" aria-hidden="true" />
        </Button>
    );
}
