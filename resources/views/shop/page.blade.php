@extends('layouts.shop')

@section('title', $page->title)
@section('description', $page->seo_description ?: strip_tags((string) $page->content))

@section('content')
    <article class="container-shop max-w-3xl pt-10">
        <h1 class="text-5xl lowercase leading-tight">{{ $page->title }}</h1>
        <div class="prose prose-neutral mt-8 max-w-none prose-headings:font-hand prose-headings:font-medium prose-a:text-cobalt">
            {!! $page->content !!}
        </div>
    </article>
@endsection
