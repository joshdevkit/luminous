<?php

declare(strict_types=1);

namespace Core\View\Engines;

use Core\Contracts\View\EngineInterface;
use Core\Support\ErrorBag;
use Core\View\ComponentCompiler;

/**
 * Lightweight Blade-like template engine with safe control-structure compilation.
 */
class TemplateEngine implements EngineInterface
{
    protected array $compilers = [];
    protected ComponentCompiler $componentCompiler;

    public function __construct(string $cachePath)
    {
        $this->registerDefaultCompilers();
        $this->componentCompiler = new ComponentCompiler();
    }

    protected function registerDefaultCompilers(): void
    {
        $this->compilers = [
            'Comments'   => '/\{\{--(.*?)--\}\}/s',
            'Echos'      => '/\{\{\s*(.+?)\s*\}\}/s',
            'RawEchos'   => '/\{!!\s*(.+?)\s*!!\}/s',
            'Php'        => '/@php\s*(.*?)\s*@endphp/s',
            'Extends'    => '/@extends\s*\([\'"](.+?)[\'"]\)/s',
            'Section' => '/@section\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+))?\s*\)/s',
            'EndSection' => '/@endsection/s',
            'Yield'      => '/@yield\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            'RenderBody' => '/@renderBody\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            'Include'    => '/@include\s*\([\'"](.+?)[\'"]\)/s',
            'Csrf'       => '/@csrf/s',
            'Method'     => '/@method\s*\(\s*[\'"](.+?)[\'"]\s*\)/s',
            'Flash'      => '/@flash\s*\(\s*[\'"](.+?)[\'"]\s*\)/s',
            'EndFlash'   => '/@endflash/s',
        ];
    }

    /**
     * Render a view file using cache when available.
     */
    public function render(string $path, array $data = []): string
    {
        $source = file_get_contents($path);
        $compiled = $this->compile($source);
        return $this->evaluateCompiledString($compiled, $data);
    }

    /**
     * Compile template source into PHP code.
     */
    protected function compile(string $contents): string
    {
        // 0. Compile components FIRST (before other directives)
        $contents = $this->componentCompiler->compile($contents);

        // 1. Remove comments
        $contents = preg_replace($this->compilers['Comments'], '', $contents);

        // 2. @extends
        $contents = preg_replace_callback($this->compilers['Extends'], function ($m) {
            return "<?php \$__extends = '" . addslashes($m[1]) . "'; ?>";
        }, $contents);

        // 3. @section (inline or block)
        $contents = preg_replace_callback('/@section\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $section = $m[1];
            $value = $m[2] ?? null;
            if ($value !== null) {
                return "<?php \$__sections['{$section}'] = {$value}; ?>";
            }
            return "<?php ob_start(); \$__currentSection = '{$section}'; ?>";
        }, $contents);

        // @endsection
        $contents = preg_replace('/@endsection/', "<?php if(isset(\$__currentSection)) { \$__sections[\$__currentSection] = ob_get_clean(); unset(\$__currentSection); } ?>", $contents);

        // @yield('title', 'Default')
        $contents = preg_replace_callback('/@yield\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $section = $m[1];
            $default = $m[2] ?? "''";
            return "<?php echo \$__sections['{$section}'] ?? ({$default}); ?>";
        }, $contents);

        // @renderBody('body')
        $contents = preg_replace_callback('/@renderBody\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*(.+?))?\s*\)/', function ($m) {
            $section = $m[1];
            $default = $m[2] ?? "''";
            return "<?php echo \$__sections['{$section}'] ?? ({$default}); ?>";
        }, $contents);

        // 6. @include
        $contents = preg_replace_callback($this->compilers['Include'], function ($m) {
            return "<?php include view('" . addslashes($m[1]) . "')->render(); ?>";
        }, $contents);

        // 7. @flash ... @endflash
        $contents = preg_replace_callback('/@flash\s*\(\s*[\'"](.+?)[\'"]\s*\)(.*?)@endflash/s', function ($m) {
            $type = addslashes($m[1]);
            $inner = $m[2];
            return "<?php if (isset(\$_SESSION['_flash.old']['{$type}'])): "
                . "\$message = \$_SESSION['_flash.old']['{$type}']; ?>\n"
                . $inner
                . "\n<?php unset(\$_SESSION['_flash.old']['{$type}']); endif; ?>";
        }, $contents);

        // 8. Control structures (safe parsing for parentheses)
        $contents = $this->compileControlStructures($contents);

        // 9. @php ... @endphp
        $contents = preg_replace($this->compilers['Php'], '<?php $1 ?>', $contents);

        // 10. @csrf
        $contents = preg_replace($this->compilers['Csrf'], '<input type="hidden" name="_token" value="<?php echo csrf_token(); ?>">', $contents);

        // 11. @method
        $contents = preg_replace_callback($this->compilers['Method'], function ($m) {
            $method = strtoupper($m[1]);
            return '<input type="hidden" name="_method" value="' . $method . '">';
        }, $contents);

        // 12. @auth / @guest
        $contents = preg_replace('/@auth\b/', "<?php if (isset(\$_SESSION['auth_id']) && \$_SESSION['auth_id']): ?>", $contents);
        $contents = preg_replace('/@endauth\b/', "<?php endif; ?>", $contents);
        $contents = preg_replace('/@guest\b/', "<?php if (!isset(\$_SESSION['auth_id']) || !\$_SESSION['auth_id']): ?>", $contents);
        $contents = preg_replace('/@endguest\b/', "<?php endif; ?>", $contents);

        // 13. @route directive - compile to router() function call
        $contents = $this->compileRouteDirective($contents);

        // 14. Raw echos {!! !!}
        $contents = preg_replace_callback($this->compilers['RawEchos'], function ($m) {
            return '<?php echo (string)(' . $m[1] . '); ?>';
        }, $contents);

        // 15. Escaped echos {{ }}
        $contents = preg_replace_callback($this->compilers['Echos'], function ($m) {
            return '<?php echo e(' . $m[1] . '); ?>';
        }, $contents);

        return $contents;
    }

    /**
     * Compile @route directive into router() function calls.
     */
    protected function compileRouteDirective(string $contents): string
    {
        return preg_replace_callback(
            '/@route\s*\(\s*([^,]+?)\s*,\s*([^,)]+?)(?:\s*,\s*(.+?))?\s*\)/s',
            function ($matches) {
                $controller = trim($matches[1]);
                $method = trim($matches[2]);
                $params = isset($matches[3]) ? trim($matches[3]) : '[]';
                return "<?php echo router({$controller}, {$method}, {$params}); ?>";
            },
            $contents
        );
    }

    /**
     * Compile control structures safely.
     */
    protected function compileControlStructures(string $contents): string
    {
        $directives = [
            'if'      => 'if',
            'elseif'  => 'elseif',
            'foreach' => 'foreach',
            'for'     => 'for',
            'while'   => 'while',
            'isset'   => 'if (isset',
            'empty'   => 'if (empty',
            'unless'  => 'if (!',
        ];

        $offset = 0;

        while (true) {
            if (!preg_match('/@(' . implode('|', array_keys($directives)) . ')\s*\(/i', $contents, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $directive = strtolower($m[1][0]);
            $matchPos = $m[0][1];
            $openParenPos = $matchPos + strlen($m[0][0]) - 1;

            $closing = $this->findMatchingParen($contents, $openParenPos);
            if ($closing === -1) {
                $offset = $openParenPos + 1;
                continue;
            }

            $inner = substr($contents, $openParenPos + 1, $closing - ($openParenPos + 1));
            $phpPrefix = $directives[$directive];

            if ($directive === 'isset') {
                $replacement = "<?php {$phpPrefix}(" . trim($inner) . ")): ?>";
            } elseif ($directive === 'empty') {
                $replacement = "<?php {$phpPrefix}(" . trim($inner) . ")): ?>";
            } elseif ($directive === 'unless') {
                $replacement = "<?php if (!(" . trim($inner) . ")): ?>";
            } else {
                $replacement = "<?php {$phpPrefix} (" . trim($inner) . "): ?>";
            }

            $before = substr($contents, 0, (int) $matchPos);
            $after = substr($contents, $closing + 1);
            $contents = $before . $replacement . $after;
            $offset = $matchPos + strlen($replacement);
        }

        $closers = [
            '/@else\b/'       => '<?php else: ?>',
            '/@endif\b/'      => '<?php endif; ?>',
            '/@endforeach\b/' => '<?php endforeach; ?>',
            '/@endfor\b/'     => '<?php endfor; ?>',
            '/@endwhile\b/'   => '<?php endwhile; ?>',
            '/@endisset\b/'   => '<?php endif; ?>',
            '/@endempty\b/'   => '<?php endif; ?>',
            '/@endunless\b/'  => '<?php endif; ?>',
            '/@endforelse\b/' => '<?php endif; ?>',
        ];

        $contents = preg_replace_callback('/@forelse\s*\((.+?)\s+as\s+(.+?)\)/s', function ($m) {
            $collection = trim($m[1]);
            $iteration  = trim($m[2]);
            return "<?php if (count({$collection}) > 0): foreach ({$collection} as {$iteration}): ?>";
        }, $contents);

        $contents = preg_replace('/@empty\b/', '<?php endforeach; else: ?>', $contents);

        foreach ($closers as $pattern => $replace) {
            $contents = preg_replace($pattern, $replace, $contents);
        }

        return $contents;
    }

    /**
     * Find matching closing parenthesis.
     */
    protected function findMatchingParen(string $s, int $openPos): int
    {
        $len = strlen($s);
        if ($openPos < 0 || $openPos >= $len || $s[$openPos] !== '(') {
            return -1;
        }

        $depth = 1;
        $i = $openPos + 1;
        $inString = false;
        $stringDelim = null;

        while ($i < $len) {
            $ch = $s[$i];

            if ($inString) {
                if ($ch === $stringDelim) {
                    $backslashes = 0;
                    $j = $i - 1;
                    while ($j >= 0 && $s[$j] === '\\') {
                        $backslashes++;
                        $j--;
                    }
                    if ($backslashes % 2 === 0) {
                        $inString = false;
                        $stringDelim = null;
                    }
                }
                $i++;
                continue;
            } else {
                if ($ch === '\'' || $ch === '"') {
                    $inString = true;
                    $stringDelim = $ch;
                    $i++;
                    continue;
                }
            }

            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }

            $i++;
        }

        return -1;
    }

    /**
     * Evaluate the compiled template string with provided data.
     */
    protected function evaluateCompiledString(string $compiled, array $data): string
    {
        $__sections = [];
        $__currentSection = null;
        $__extends = null;

        $errors = $_SESSION['errors'] ?? new ErrorBag();
       
        if (is_array($errors)) {
            $errors = new ErrorBag($errors);
        }
        ${'errors'} = $errors;

        extract($data, EXTR_SKIP);

        $tempFile = sys_get_temp_dir() . '/blade_' . md5($compiled . uniqid('', true)) . '.php';
        file_put_contents($tempFile, $compiled);

        ob_start();

        try {
            include $tempFile;
        } catch (\Throwable $e) {
            ob_get_clean();
            @unlink($tempFile);
            throw $e;
        }

        @unlink($tempFile);

        if (isset($__extends) && $__extends) {
            $parentPath = $this->findViewPath($__extends);
            $parentSource = file_get_contents($parentPath);
            $parentCompiled = $this->compile($parentSource);

            $parentTempFile = sys_get_temp_dir() . '/blade_' . md5($parentCompiled . uniqid('', true)) . '.php';
            file_put_contents($parentTempFile, $parentCompiled);

            ob_start();
            include $parentTempFile;
            @unlink($parentTempFile);
            $output = ltrim(ob_get_clean());
        } else {
            $output = ltrim(ob_get_clean());
        }

        if (isset($_SESSION['errors'])) {
            unset($_SESSION['errors']);
        }

        return $output;
    }

    protected function findViewPath(string $view): string
    {
        $base = base_path('resources/views');
        $viewPath = str_replace('.', '/', $view);
        return rtrim($base, '/\\') . '/' . ltrim($viewPath, '/\\') . '.blade.php';
    }
}