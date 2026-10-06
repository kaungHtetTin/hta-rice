<?php
declare(strict_types=1);
namespace App\Services;

/** Shared, bounded GET filters and pagination for business lists. */
final class ListPage
{
    public array $filters = [];
    public array $errors = [];
    public int $size;
    public int $page = 1;
    public int $pages = 1;
    public int $count = 0;

    public function __construct(public string $route)
    {
        $size = self::integer('per_page', 25);
        $this->size = in_array($size, [10,25,50,100], true) ? $size : 25;
        $this->filters['per_page'] = $this->size;
    }
    public static function value(string $key): string
    {
        return is_string($_GET[$key] ?? null) ? trim($_GET[$key]) : '';
    }
    private static function integer(string $key, int $default = 0): int
    {
        $value = self::value($key);
        return ctype_digit($value) && strlen($value) <= 9 ? (int)$value : $default;
    }
    public function text(string $key): string { return $this->filters[$key] = mb_substr(self::value($key), 0, 200); }
    public function id(string $key): int { return $this->filters[$key] = self::integer($key); }
    public function choice(string $key, array $choices): string
    {
        $value = self::value($key);
        return $this->filters[$key] = in_array($value, $choices, true) ? $value : '';
    }
    public function dates(): array
    {
        foreach(['from','to'] as $key) {
            $value = self::value($key);
            $this->filters[$key] = $value;
            if($value !== '') {
                try {
                    if(!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) throw new \InvalidArgumentException();
                    valid_date($value);
                }
                catch(\InvalidArgumentException) { $this->errors[] = t($key==='from'?'Enter a valid From date.':'Enter a valid To date.'); $this->filters[$key]=''; }
            }
        }
        if($this->filters['from'] && $this->filters['to'] && $this->filters['from'] > $this->filters['to']) $this->errors[] = t('From date must be on or before To date.');
        return [$this->filters['from'], $this->filters['to']];
    }
    public function limit(int $count): string
    {
        $this->count = $count;
        $this->pages = max(1, (int)ceil($count / $this->size));
        $this->page = min($this->pages, max(1, self::integer('page', 1)));
        return ' LIMIT '.$this->size.' OFFSET '.(($this->page-1)*$this->size);
    }
    public function link(int $page): string
    {
        return url($this->route).'?'.http_build_query($this->filters + ['page'=>$page]);
    }
    public static function like(string $text): string
    {
        // Use an explicit escape character so %, _ and ! are literal searches.
        return '%'.str_replace(['!','%','_'], ['!!','!%','!_'], $text).'%';
    }
}
