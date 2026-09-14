<?php

it('keeps shared sidebar scroll surfaces intentional', function () {
    $layout = file_get_contents(
        resource_path('js/layouts/app/app-sidebar-layout.tsx'),
    );
    $sidebar = file_get_contents(
        resource_path('js/components/ui/sidebar.tsx'),
    );
    $general = file_get_contents(
        resource_path('js/pages/settings/general.tsx'),
    );

    expect($layout)
        ->toContain('className="overflow-x-clip overflow-y-clip"')
        ->not->toContain('className="overflow-x-hidden"');

    expect($sidebar)
        ->toContain(
            'peer-data-[variant=inset]:min-h-[calc(100svh_-_var(--app-banner-height,0px)_-_(--spacing(4)))]',
        )
        ->not->toContain(')-(--spacing(4))]')
        ->toContain('flex min-h-0 flex-1 flex-col gap-2 overflow-auto');

    expect($general)->toContain(
        'grid max-h-72 gap-2 overflow-y-auto rounded-md border p-3 sm:grid-cols-2',
    );
});
