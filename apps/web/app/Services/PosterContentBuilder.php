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
        $sourceTitle = trim((string) ($source['title'] ?: $source['group_name'] ?? 'Интерьерная картина'));
        $subject = Str::of($sourceTitle)
            ->replaceMatches('/\s+(30|40|50)\s*[xх×]\s*(30|40|50)\s*(см)?/iu', '')
            ->replaceMatches('/^(картина|постер|холст)(\s+(картина|постер|холст))*/iu', '')
            ->squish()
            ->toString();
        $subject = $subject !== '' ? Str::ucfirst($subject) : 'Интерьерная картина';
        $title = 'Картина на холсте «'.$subject.'»';
        $visualText = $this->visualText($source['visual_analysis'] ?? []);
        $taxonomy = $this->taxonomy(mb_strtolower($title.' '.$visualText));
        $sourceColors = $this->sourceColors($source);
        $primaryColor = $sourceColors !== [] ? implode(', ', $sourceColors) : $taxonomy['color'];
        $shortDescription = $visualText !== ''
            ? Str::limit($visualText, 180)
            : 'Готовая интерьерная картина с печатью на холсте и деревянным подрамником.';

        return [
            'title' => $title,
            'short_description' => $shortDescription,
            'description' => sprintf(
                'Картина «%s» напечатана на холсте и натянута на деревянный подрамник. %s Это интерьерная печать, а не ручная живопись. Картина готова к размещению на стене.',
                $subject,
                $visualText !== '' ? rtrim($visualText, '.').'.' : ''
            ),
            'seo_title' => Str::limit($subject.' — картина на холсте | Токамаки', 60, ''),
            'seo_description' => Str::limit('Купить картину «'.$subject.'» с печатью на холсте и деревянным подрамником. Размеры 30×40 и 40×50 см.', 160, ''),
            'category' => $taxonomy['category'],
            'style' => $taxonomy['style'],
            'room' => $taxonomy['room'],
            'primary_color' => $primaryColor,
            'orientation' => $taxonomy['orientation'],
            'search_keywords' => Poster::normalizeSearchKeywords([
                $taxonomy['category'], $taxonomy['style'], $taxonomy['room'], $sourceColors === [] ? $taxonomy['color'] : null,
                ...$sourceColors, 'картина на холсте', 'интерьерная картина', 'постер на холсте',
                ...preg_split('/[^\p{L}\p{N}-]+/u', mb_strtolower($title), -1, PREG_SPLIT_NO_EMPTY),
            ]),
        ];
    }

    /** @param  array<string, mixed>  $source */
    public function primaryColor(array $source): ?string
    {
        $colors = $this->sourceColors($source);

        return $colors === [] ? null : implode(', ', $colors);
    }

    /**
     * @param  array<string, mixed>  $source
     * @return array<int, string>
     */
    public function sourceColors(array $source): array
    {
        foreach ($source['characteristics'] ?? [] as $characteristic) {
            if (! is_array($characteristic)) {
                continue;
            }

            $isColor = (int) ($characteristic['id'] ?? 0) === 14177449
                || mb_strtolower(trim((string) ($characteristic['name'] ?? ''))) === 'цвет';

            if (! $isColor) {
                continue;
            }

            $value = $characteristic['value'] ?? [];
            $values = is_array($value) ? $value : (preg_split('/[,;]+/u', (string) $value) ?: []);

            return collect($values)
                ->flatMap(fn (mixed $color): array => preg_split('/[,;]+/u', (string) $color, -1, PREG_SPLIT_NO_EMPTY) ?: [])
                ->map(fn (string $color): string => mb_strtolower(trim($color)))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [];
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
            default => 'Интерьерные картины',
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
