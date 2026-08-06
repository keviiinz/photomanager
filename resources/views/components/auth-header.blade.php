@props([
    'title',
    'description',
])

<div class="flex w-full flex-col text-center">
    <flux:heading size="xl" class="text-[#3d3835]" style="font-family: 'Instrument Serif', ui-serif, serif;">
        {{ $title }}
    </flux:heading>
    <flux:subheading class="text-[#8a8280]">{{ $description }}</flux:subheading>
</div>
