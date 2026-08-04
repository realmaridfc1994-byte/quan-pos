<x-filament-panels::page>
    <x-filament-widgets::widgets
        :columns="$this->getHeaderWidgetsColumns()"
        :widgets="$this->getVisibleHeaderWidgets()"
    />
</x-filament-panels::page>
