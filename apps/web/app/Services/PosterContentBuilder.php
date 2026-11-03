<?php

namespace App\Services;

use App\Models\Poster;
use Illuminate\Support\Str;

class PosterContentBuilder
{
    /**
     * @param  array<string, mixed>  $source
     * @return array<string, mixed>
     */
    public function build(array $source): array
    {
        $sourceTitle = trim((string) ($source['title'] ?: $source['group_name'] ?? 'Интерьерный постер'));
        $subject = Str::of($sourceTitle)
            ->replaceMatches('/\s+(30|40|50)\s*[xх×]\s*(30|40|50)\s*(см)?/iu', '')
            ->replaceMatches('/^(картина|постер|холст)(\s+(картина|постер|холст))*/iu', '')
            ->squish()
            ->toString();
        $subject = $subject !== '' ? Str::ucfirst($subject) : 'Интерьерный постер';
        $title = 'Постер «'.$subject.'»';
        $visualText = $this->visualText($source['visual_analysis'] ?? []);
        $taxonomy = $this->taxonomy(mb_strtolower($title.' '.$visualText));
        $shortDescription = $visualText !== ''
            ? Str::limit($visualText, 180)
            : 'Готовый интерьерный постер на подрамнике для дома, офиса или подарка.';

        return [
            'title' => $title,
            'short_description' => $shortDescription,
            'description' => sprintf(
                'Интерьерный постер «%s» напечатан на холсте и натянут на деревянный подрамник. %s Картина готова к размещению на стене и подойдёт для современного интерьера или подарка.',
                $title,
                $visualText !== '' ? rtrim($visualText, '.').'.' : ''
            ),
            'seo_title' => Str::limit($title.' — постер на подрамнике | Токамаки', 60, ''),
            'seo_description' => Str::limit('Купить постер «'.$title.'» на подрамнике. Размеры 30×40 и 40×50 см. Интерьерная печать Tokamaki.', 160, ''),
            'category' => $taxonomy['category'],
            'style' => $taxonomy['style'],
            'room' => $taxonomy['room'],
            'primary_color' => $taxonomy['color'],
            'orientation' => $taxonomy['orientation'],
            'search_keywords' => Poster::normalizeSearchKeywords([
                $taxonomy['category'], $taxonomy['style'], $taxonomy['room'], $taxonomy['color'],
                ...preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY),
            ]),
        ];
    }

    private function visualText(mixed $analysis): string
    {
        if (! is_array($analysis)) {
            return '';
        }

        foreach (['description', 'summary', 'scene', 'subject'] as $key) {
            if (isset($analysis[$key]) && is_string($analysis[$key])) {
                return Str::of($analysis[$key])->squish()->toString();
            }
        }

        return '';
    }

    /** @return array{category: string, style: string, room: string, color: ?string, orientation: string} */
    private function taxonomy(string $text): array
    {
        $category = match (true) {
            Str::contains($text, ['кот', 'кошк', 'собак', 'животн', 'птиц']) => 'Животные',
            Str::contains($text, ['авто', 'машин', 'bmw', 'mercedes', 'мазда', 'спорткар']) => 'Автомобили',
            Str::contains($text, ['лес', 'гор', 'море', 'океан', 'пейзаж', 'цвет', 'растен']) => 'Природа',
            Str::contains($text, ['город', 'улиц', 'москва', 'париж', 'архитект']) => 'Города',
            Str::contains($text, ['абстрак', 'геометр', 'линии', 'формы']) => 'Абстракция',
            Str::contains($text, ['детск', 'мульт', 'ребен']) => 'Для детской',
            default => 'Интерьерные постеры',
        };

        return [
            'category' => $category,
            'style' => Str::contains($text, ['минимал', 'лаконич']) ? 'Минимализм' : 'Современный',
            'room' => $category === 'Для детской' ? 'Детская' : 'Гостиная',
            'color' => collect(['бежевый', 'синий', 'зелёный', 'красный', 'чёрный', 'белый', 'розовый'])
                ->first(fn (string $color): bool => Str::contains($text, $color)),
            'orientation' => Str::contains($text, ['горизонталь', 'панорам']) ? 'horizontal' : 'vertical',
        ];
    }
}
