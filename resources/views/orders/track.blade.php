@extends('layouts.shop')
@use('App\Support\Money')
@use('App\Enums\OrderStatus')

@section('title', 'Sipariş '.$order->number)
@section('noindex', '1')

@php
    $steps = $order->timelineSteps();
    $reached = $order->histories->pluck('to_status')->all();
    $isClosed = in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Refunded, OrderStatus::PaymentFailed, OrderStatus::PendingPayment], true);
    $currentIndex = array_search($order->status === OrderStatus::Completed ? end($steps) : $order->status, $steps, true);
    $whenReached = fn (OrderStatus $s) => $order->histories->firstWhere('to_status', $s)?->created_at;
@endphp

@section('content')
    <div class="container-shop max-w-3xl pt-8 sm:pt-12">
        @if ($justPaid)
            <div class="mb-8 flex items-center gap-4 rounded-[1.75rem] bg-ink p-5 text-paper sm:p-6">
                <x-doodle.sun class="size-16 shrink-0 text-paper" />
                <div>
                    <p class="font-hand text-2xl sm:text-3xl">teşekkürler {{ mb_strtolower($order->firstName()) }}!</p>
                    <p class="mt-1 text-sm text-paper/80">Siparişiniz alındı. Bu sayfanın bağlantısını {{ $order->customer_email }} adresine de gönderdik.</p>
                </div>
            </div>
        @endif

        <p class="text-sm text-ink-soft">Sipariş {{ $order->number }} · {{ $order->created_at->translatedFormat('j F Y') }}</p>
        <h1 class="mt-1 text-4xl leading-tight sm:text-5xl">{{ mb_strtolower($order->status->getLabel()) }}</h1>
        <p class="mt-2 text-ink-soft">{{ $order->status->customerMessage() }}</p>

        @if ($order->status === OrderStatus::PaymentFailed || $order->status === OrderStatus::PendingPayment)
            <form method="POST" action="{{ route('payment.retry', $order->tracking_token) }}" class="mt-5">
                @csrf
                <button class="btn-primary">ödemeyi tamamla</button>
            </form>
        @endif

        @unless ($isClosed)
            <ol @class(['mt-10 grid gap-0', 'sm:grid-cols-4' => count($steps) === 4, 'sm:grid-cols-3' => count($steps) === 3])>
                @foreach ($steps as $index => $step)
                    @php($done = $currentIndex !== false && $index <= $currentIndex)
                    <li class="relative flex gap-4 pb-8 sm:flex-col sm:gap-3 sm:pb-0 sm:pr-4">
                        @if (! $loop->last)
                            <span @class(['absolute left-[15px] top-8 h-[calc(100%-2rem)] w-0.5 sm:left-8 sm:top-[15px] sm:h-0.5 sm:w-[calc(100%-2rem)]', 'bg-ink' => $currentIndex !== false && $index < $currentIndex, 'bg-line' => ! ($currentIndex !== false && $index < $currentIndex)])></span>
                        @endif
                        <span @class(['relative z-10 flex size-8 shrink-0 items-center justify-center rounded-full border-[1.5px]', 'border-ink bg-ink text-paper' => $done, 'border-line bg-paper text-ink-faint' => ! $done])>
                            @if ($done)<x-glyph name="check" class="size-4" />@else<span class="text-xs">{{ $index + 1 }}</span>@endif
                        </span>
                        <div>
                            <p @class(['font-medium', 'text-ink-faint' => ! $done])>{{ $step->getLabel() }}</p>
                            @if ($at = $whenReached($step))
                                <p class="text-sm text-ink-soft">{{ $at->translatedFormat('j F, H:i') }}</p>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endunless

        @if ($order->cargoTrackingUrl() || $order->cargo_tracking_number)
            <div class="mt-8 flex flex-wrap items-center justify-between gap-4 rounded-2xl border-[1.5px] border-ink p-5">
                <div class="flex items-center gap-3">
                    <x-glyph name="truck" class="size-6" />
                    <div>
                        <p class="font-medium">{{ $order->cargo_company?->getLabel() }}</p>
                        <p class="text-sm text-ink-soft">Takip no: {{ $order->cargo_tracking_number }}</p>
                    </div>
                </div>
                @if ($order->cargoTrackingUrl())
                    <a href="{{ $order->cargoTrackingUrl() }}" target="_blank" rel="noopener" class="btn-primary py-2.5">kargom nerede?</a>
                @endif
            </div>
        @endif

        @php($notes = $order->histories->filter(fn ($h) => $h->customer_notified && $h->note))
        @if ($notes->isNotEmpty())
            <div class="mt-8 space-y-3">
                @foreach ($notes->reverse() as $history)
                    <div class="rounded-2xl bg-paper-deep px-4 py-3 text-[15px]">
                        <p class="text-xs text-ink-faint">{{ $history->created_at->translatedFormat('j F, H:i') }}</p>
                        <p class="mt-1">{{ $history->note }}</p>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="mt-10 grid gap-8 sm:grid-cols-[1fr_260px]">
            <section>
                <h2 class="text-2xl">ürünler</h2>
                <ul class="mt-3 divide-y divide-line border-y border-line">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-4 py-3 text-[15px]">
                            <div>
                                <p class="font-medium">{{ $item->name }} <span class="font-normal text-ink-soft">× {{ $item->quantity }}</span></p>
                                @if ($item->variant_name)<p class="text-sm text-ink-soft">{{ $item->variant_name }}</p>@endif
                                @if ($item->session)
                                    <p class="text-sm text-ink-soft">{{ $item->session->displayLabel() }}</p>
                                    @if ($item->participants)<p class="text-sm text-ink-faint">Katılımcılar: {{ implode(', ', $item->participants) }}</p>@endif
                                @endif
                            </div>
                            <p class="shrink-0">{{ Money::format($item->line_total) }}</p>
                        </li>
                    @endforeach
                </ul>
                <dl class="mt-4 space-y-1.5 text-[15px]">
                    @if ($order->requires_shipping)<div class="flex justify-between"><dt class="text-ink-soft">Kargo</dt><dd>{{ $order->shipping_total ? Money::format($order->shipping_total) : 'ücretsiz' }}</dd></div>@endif
                    <div class="flex justify-between text-lg font-medium"><dt>Toplam</dt><dd>{{ Money::format($order->total) }}</dd></div>
                    @if ($order->refunded_total)<div class="flex justify-between text-ink-soft"><dt>İade edilen</dt><dd>{{ Money::format($order->refunded_total) }}</dd></div>@endif
                </dl>
            </section>
            <aside class="space-y-5 text-[15px]">
                @if ($order->requires_shipping)
                    <div>
                        <p class="font-hand text-xl">teslimat</p>
                        <p class="mt-1 text-ink-soft">{{ $order->shipping_name }}<br>{{ $order->maskedAddress() }}</p>
                    </div>
                @endif
                @if ($order->hasWorkshops() && $order->status->isPaid())
                    <a href="{{ route('orders.calendar', $order->tracking_token) }}" class="btn-outline w-full py-2.5"><x-glyph name="calendar" class="size-4" /> takvime ekle</a>
                @endif
                <div>
                    <p class="font-hand text-xl">yardım</p>
                    <p class="mt-1 text-ink-soft">Siparişinizle ilgili bir sorunuz varsa sipariş numaranızla bize yazın.</p>
                    <a href="https://wa.me/{{ $settings['whatsapp_number'] }}?text={{ rawurlencode('Merhaba, '.$order->number.' numaralı siparişim hakkında yazıyorum.') }}" class="mt-2 inline-flex items-center gap-2 underline-offset-4 hover:underline" rel="noopener"><x-glyph name="whatsapp" class="size-4" /> WhatsApp</a>
                </div>
            </aside>
        </div>
    </div>
@endsection
