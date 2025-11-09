<?php

// ============================================
// 1. Core/Support/Paginator.php
// ============================================

namespace Core\Support;

class Paginator implements \JsonSerializable
{
    protected Collection $items;
    protected int $total;
    protected int $perPage;
    protected int $currentPage;
    protected int $lastPage;
    protected ?int $from;
    protected ?int $to;
    protected string $path;
    
    public function __construct(
        Collection $items,
        int $total,
        int $perPage,
        int $currentPage,
        array $options = []
    ) {
        $this->items = $items;
        $this->total = $total;
        $this->perPage = $perPage;
        $this->currentPage = $currentPage;
        $this->lastPage = max((int) ceil($total / $perPage), 1);
        $this->path = $options['path'] ?? $this->getCurrentPath();
        
        $this->setFromTo();
    }
    
    protected function setFromTo(): void
    {
        if ($this->total === 0) {
            $this->from = null;
            $this->to = null;
        } else {
            $this->from = (($this->currentPage - 1) * $this->perPage) + 1;
            $this->to = min($this->from + $this->items->count() - 1, $this->total);
        }
    }
    
    protected function getCurrentPath(): string
    {
        $url = $_SERVER['REQUEST_URI'] ?? '/';
        return strtok($url, '?');
    }
    
    public function items(): Collection
    {
        return $this->items;
    }
    
    public function total(): int
    {
        return $this->total;
    }
    
    public function perPage(): int
    {
        return $this->perPage;
    }
    
    public function currentPage(): int
    {
        return $this->currentPage;
    }
    
    public function lastPage(): int
    {
        return $this->lastPage;
    }
    
    public function from(): ?int
    {
        return $this->from;
    }
    
    public function to(): ?int
    {
        return $this->to;
    }
    
    public function count(): int
    {
        return $this->items->count();
    }
    
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }
    
    public function isNotEmpty(): bool
    {
        return $this->items->isNotEmpty();
    }
    
    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->lastPage;
    }
    
    public function onFirstPage(): bool
    {
        return $this->currentPage <= 1;
    }
    
    public function onLastPage(): bool
    {
        return $this->currentPage >= $this->lastPage;
    }
    
    public function url(int $page): string
    {
        if ($page <= 0) {
            $page = 1;
        }
        
        $parameters = ['page' => $page];
        
        return $this->path . '?' . http_build_query($parameters);
    }
    
    public function nextPageUrl(): ?string
    {
        if ($this->hasMorePages()) {
            return $this->url($this->currentPage + 1);
        }
        
        return null;
    }
    
    public function previousPageUrl(): ?string
    {
        if ($this->currentPage > 1) {
            return $this->url($this->currentPage - 1);
        }
        
        return null;
    }
    
    public function firstPageUrl(): string
    {
        return $this->url(1);
    }
    
    public function lastPageUrl(): string
    {
        return $this->url($this->lastPage);
    }
    
    public function getPageRange(int $onEachSide = 3): array
    {
        $window = $onEachSide * 2;
        
        if ($this->lastPage < $window + 6) {
            return $this->getSmallSlider();
        }
        
        return $this->getUrlSlider($onEachSide);
    }
    
    protected function getSmallSlider(): array
    {
        return [
            'first' => $this->getUrlRange(1, $this->lastPage),
            'slider' => null,
            'last' => null,
        ];
    }
    
    protected function getUrlSlider(int $onEachSide): array
    {
        $window = $onEachSide * 2;
        
        if ($this->currentPage <= $window) {
            return $this->getSliderTooCloseToBeginning($window);
        } elseif ($this->currentPage >= $this->lastPage - $window) {
            return $this->getSliderTooCloseToEnding($window);
        }
        
        return $this->getFullSlider($onEachSide);
    }
    
    protected function getSliderTooCloseToBeginning(int $window): array
    {
        return [
            'first' => $this->getUrlRange(1, $window + 2),
            'slider' => null,
            'last' => $this->getUrlRange($this->lastPage - 1, $this->lastPage),
        ];
    }
    
    protected function getSliderTooCloseToEnding(int $window): array
    {
        return [
            'first' => $this->getUrlRange(1, 2),
            'slider' => null,
            'last' => $this->getUrlRange($this->lastPage - ($window + 2), $this->lastPage),
        ];
    }
    
    protected function getFullSlider(int $onEachSide): array
    {
        return [
            'first' => $this->getUrlRange(1, 2),
            'slider' => $this->getUrlRange(
                $this->currentPage - $onEachSide,
                $this->currentPage + $onEachSide
            ),
            'last' => $this->getUrlRange($this->lastPage - 1, $this->lastPage),
        ];
    }
    
    protected function getUrlRange(int $start, int $end): array
    {
        $urls = [];
        
        for ($page = $start; $page <= $end; $page++) {
            $urls[$page] = $this->url($page);
        }
        
        return $urls;
    }
    
    public function links(): string
    {
        if ($this->lastPage <= 1) {
            return '';
        }
        
        $html = '<nav aria-label="Pagination"><ul class="pagination">';
        
        if ($this->onFirstPage()) {
            $html .= '<li class="disabled"><span>Previous</span></li>';
        } else {
            $html .= '<li><a href="' . $this->previousPageUrl() . '">Previous</a></li>';
        }
        
        $slider = $this->getPageRange();
        
        if ($slider['first']) {
            foreach ($slider['first'] as $page => $url) {
                $html .= $this->getPageLinkHtml($page, $url);
            }
        }
        
        if ($slider['slider']) {
            $html .= '<li class="disabled"><span>...</span></li>';
            
            foreach ($slider['slider'] as $page => $url) {
                $html .= $this->getPageLinkHtml($page, $url);
            }
        }
        
        if ($slider['last']) {
            if (!$slider['slider']) {
                $html .= '<li class="disabled"><span>...</span></li>';
            }
            
            foreach ($slider['last'] as $page => $url) {
                $html .= $this->getPageLinkHtml($page, $url);
            }
        }
        
        if ($this->hasMorePages()) {
            $html .= '<li><a href="' . $this->nextPageUrl() . '">Next</a></li>';
        } else {
            $html .= '<li class="disabled"><span>Next</span></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
    
    protected function getPageLinkHtml(int $page, string $url): string
    {
        if ($page === $this->currentPage) {
            return '<li class="active"><span>' . $page . '</span></li>';
        }
        
        return '<li><a href="' . $url . '">' . $page . '</a></li>';
    }
    
    public function toArray(): array
    {
        return [
            'data' => $this->items->toArray(),
            'current_page' => $this->currentPage,
            'first_page_url' => $this->firstPageUrl(),
            'from' => $this->from,
            'last_page' => $this->lastPage,
            'last_page_url' => $this->lastPageUrl(),
            'next_page_url' => $this->nextPageUrl(),
            'path' => $this->path,
            'per_page' => $this->perPage,
            'prev_page_url' => $this->previousPageUrl(),
            'to' => $this->to,
            'total' => $this->total,
        ];
    }
    
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }
    
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
    
    public function __toString(): string
    {
        return $this->toJson();
    }
}