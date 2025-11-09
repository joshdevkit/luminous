<?php

declare(strict_types=1);

namespace Core\View\Engines;

use Core\Contracts\View\EngineInterface;
use Core\Support\ErrorBag;

/**
 * Lightweight Blade-like template engine with safe control-structure compilation.
 */
class TemplateEngineWithCache implements EngineInterface
{
    protected string $cachePath;
    protected array $compilers = [];

    public function __construct(string $cachePath)
    {
        $this->cachePath = rtrim($cachePath, '/\\');
        $this->registerDefaultCompilers();
    }

    protected function registerDefaultCompilers(): void
    {
        $this->compilers = [
            'Comments'   => '/\{\{--(.*?)--\}\}/s',
            'Echos'      => '/\{\{\s*(.+?)\s*\}\}/s',
            'RawEchos'   => '/\{!!\s*(.+?)\s*!!\}/s',
            'Php'        => '/@php\s*(.*?)\s*@endphp/s',
            'Extends'    => '/@extends\s*\([\'"](.+?)[\'"]\)/s',
            'Section'    => '/@section\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            'EndSection' => '/@endsection/s',
            'Yield'      => '/@yield\s*\(\s*[\'"](.+?)[\'"]\s*(?:,\s*[\'"](.+?)[\'"])?\s*\)/s',
            'Include'    => '/@include\s*\([\'"](.+?)[\'"]\)/s',
            'Csrf'       => '/@csrf/s',
            'Flash'      => '/@flash\s*\(\s*[\'"](.+?)[\'"]\s*\)/s',
            'EndFlash'   => '/@endflash/s',
        ];
    }

    /**
     * Render a view file using cache when available.
     */
    public function render(string $path, array $data = []): string
    {
        $compiledPath = $this->getCompiledPath($path);

        if ($this->isExpired($path, $compiledPath)) {
            $source = file_get_contents($path);
            $compiled = $this->compile($source);
            $this->cache($path, $compiled);
        }

        return $this->evaluateCompiled($compiledPath, $data);
    }

    /**
     * Compile template source into PHP code.
     */
    protected function compile(string $contents): string
    {
        // 1. Remove comments
        $contents = preg_replace($this->compilers['Comments'], '', $contents);

        // 2. @extends
        $contents = preg_replace_callback($this->compilers['Extends'], function ($m) {
            return "<?php \$__extends = '" . addslashes($m[1]) . "'; ?>";
        }, $contents);

        // 3. @section (inline or block)
        $contents = preg_replace_callback($this->compilers['Section'], function ($m) {
            $section = $m[1] ?? '';
            $value = $m[2] ?? null;
            if ($value !== null) {
                return "<?php \$__sections['{$section}'] = '" . addslashes($value) . "'; ?>";
            }
            return "<?php ob_start(); \$__currentSection = '{$section}'; ?>";
        }, $contents);

        // 4. @endsection
        $contents = preg_replace($this->compilers['EndSection'], "<?php \$__sections[\$__currentSection] = ob_get_clean(); ?>", $contents);

        // 5. @yield
        $contents = preg_replace_callback($this->compilers['Yield'], function ($m) {
            $section = $m[1];
            $default = $m[2] ?? '';
            return "<?php echo \$__sections['{$section}'] ?? '" . addslashes($default) . "'; ?>";
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

        // 11. @auth / @guest
        $contents = preg_replace('/@auth\b/', "<?php if (isset(\$_SESSION['auth_id']) && \$_SESSION['auth_id']): ?>", $contents);
        $contents = preg_replace('/@endauth\b/', "<?php endif; ?>", $contents);
        $contents = preg_replace('/@guest\b/', "<?php if (!isset(\$_SESSION['auth_id']) || !\$_SESSION['auth_id']): ?>", $contents);
        $contents = preg_replace('/@endguest\b/', "<?php endif; ?>", $contents);

        // 12. @route directive - compile to router() function call
        $contents = $this->compileRouteDirective($contents);

        // 13. Raw echos {!! !!}
        $contents = preg_replace_callback($this->compilers['RawEchos'], function ($m) {
            return '<?php echo (string)(' . $m[1] . '); ?>';
        }, $contents);

        // 14. Escaped echos {{ }}
        $contents = preg_replace_callback($this->compilers['Echos'], function ($m) {
            return '<?php echo e(' . $m[1] . '); ?>';
        }, $contents);

        return $contents;
    }

    /**
     * Compile @route directive into router() function calls.
     * 
     * Supports:
     * - @route('Controller', 'method')
     * - @route('Controller', 'method', ['id' => 1])
     * - @route('Controller', 'method', $params)
     */
    protected function compileRouteDirective(string $contents): string
    {
        // Pattern matches: @route('Controller', 'method') or @route('Controller', 'method', [...])
        return preg_replace_callback(
            '/@route\s*\(\s*([^,]+?)\s*,\s*([^,)]+?)(?:\s*,\s*(.+?))?\s*\)/s',
            function ($matches) {
                $controller = trim($matches[1]);
                $method = trim($matches[2]);
                $params = isset($matches[3]) ? trim($matches[3]) : '[]';

                // Build the router() function call
                return "<?php echo router({$controller}, {$method}, {$params}); ?>";
            },
            $contents
        );
    }

    /**
     * Compile control structures safely so nested parentheses (e.g. function calls) don't break.
     *
     * This finds opening directives like @if( ... ), @foreach( ... ) and replaces the entire "(...)" span
     * using a balanced-parenthesis scanner to capture the full expression.
     */
    protected function compileControlStructures(string $contents): string
    {
        // list of opening directives that have parenthesized expressions
        $directives = [
            'if'      => 'if',
            'elseif'  => 'elseif',
            'foreach' => 'foreach',
            'for'     => 'for',
            'while'   => 'while',
            'isset'   => 'if (isset',
            'empty'   => 'if (empty',
            'unless'  => 'if (!', // support @unless(...) => if (!(...))
        ];

        // We'll iterate and replace occurrences one by one to avoid index shifting issues
        $offset = 0;
        $len = strlen($contents);

        while (true) {
            // find next opening directive (any of the above) using regex with offset
            if (!preg_match('/@(' . implode('|', array_keys($directives)) . ')\s*\(/i', $contents, $m, PREG_OFFSET_CAPTURE, $offset)) {
                break;
            }

            $directive = strtolower($m[1][0]);
            $matchPos = $m[0][1]; // start of "@directive("
            $openParenPos = $matchPos + strlen($m[0][0]) - 1; // position of '('

            // find matching closing parenthesis position
            $closing = $this->findMatchingParen($contents, $openParenPos);
            if ($closing === -1) {
                // unbalanced — skip this match (avoid infinite loop). Move offset past this point.
                $offset = $openParenPos + 1;
                continue;
            }

            // extract the inner expression between ( and )
            $inner = substr($contents, $openParenPos + 1, $closing - ($openParenPos + 1));

            // special handling for @isset/@empty and @unless
            $phpPrefix = $directives[$directive];

            if ($directive === 'isset') {
                $replacement = "<?php {$phpPrefix}(" . trim($inner) . ")): ?>"; // phpPrefix already has "if (isset"
            } elseif ($directive === 'empty') {
                $replacement = "<?php {$phpPrefix}(" . trim($inner) . ")): ?>";
            } elseif ($directive === 'unless') {
                // @unless(expr) => if (!(expr)):
                $replacement = "<?php if (!(" . trim($inner) . ")): ?>";
            } else {
                $replacement = "<?php {$phpPrefix} (" . trim($inner) . "): ?>";
            }

            // Replace the entire span from matchPos up to closing parenthesis (inclusive)
            $before = substr($contents, 0, (int) $matchPos);
            $after = substr($contents, $closing + 1);

            $contents = $before . $replacement . $after;

            // Move offset to after the inserted replacement to continue scanning
            $offset = $matchPos + strlen($replacement);
            // continue loop
        }

        // Now handle simple closers and else
        $closers = [
            '/@else\b/'       => '<?php else: ?>',
            '/@endif\b/'      => '<?php endif; ?>',
            '/@endforeach\b/' => '<?php endforeach; ?>',
            '/@endfor\b/'     => '<?php endfor; ?>',
            '/@endwhile\b/'   => '<?php endwhile; ?>',
            '/@endisset\b/'   => '<?php endif; ?>',
            '/@endempty\b/'   => '<?php endif; ?>',
            '/@endunless\b/'  => '<?php endif; ?>',
            '/@endforelse\b/'   => '<?php endif; ?>',
        ];

        // foreach ($closers as $pattern => $replace) {
        //     $contents = preg_replace($pattern, $replace, $contents);
        // }

        $contents = preg_replace_callback('/@forelse\s*\((.+?)\s+as\s+(.+?)\)/s', function ($m) {
            $collection = trim($m[1]);
            $iteration  = trim($m[2]);
            // Proper fix: use count() instead of empty() for Collection
            return "<?php if (count({$collection}) > 0): foreach ({$collection} as {$iteration}): ?>";
        }, $contents);

        $contents = preg_replace('/@empty\b/', '<?php endforeach; else: ?>', $contents);


        // Apply remaining simple closers
        foreach ($closers as $pattern => $replace) {
            $contents = preg_replace($pattern, $replace, $contents);
        }

        return $contents;
    }

    /**
     * Find matching closing parenthesis for the '(' at $openPos.
     * Returns the index of the matching ')' or -1 when not found.
     *
     * This function is string-aware: it skips over quoted strings and respects escaping.
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

            // handle string start/end (respect escape)
            if ($inString) {
                if ($ch === $stringDelim) {
                    // check not escaped
                    $backslashes = 0;
                    $j = $i - 1;
                    while ($j >= 0 && $s[$j] === '\\') {
                        $backslashes++;
                        $j--;
                    }
                    if ($backslashes % 2 === 0) {
                        // not escaped
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

        return -1; // no match
    }

    /**
     * Evaluate the compiled template file with provided data.
     */
    protected function evaluateCompiled(string $path, array $data): string
    {
        $__sections = [];
        $__currentSection = null;
        $__extends = null;

        // Ensure session started for errors/flash
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Provide $errors as ErrorBag
        $errors = $_SESSION['errors'] ?? new ErrorBag();
        if (is_array($errors)) {
            $errors = new ErrorBag($errors);
        }
        /** expose $errors variable to templates */
        ${'errors'} = $errors;

        extract($data, EXTR_SKIP);

        ob_start();

        try {
            include $path;
        } catch (\Throwable $e) {
            ob_get_clean();
            throw $e;
        }

        // handle extends by rendering parent
        if (isset($__extends) && $__extends) {
            $parentPath = $this->findViewPath($__extends);
            $parentCompiled = $this->getCompiledPath($parentPath);
            if ($this->isExpired($parentPath, $parentCompiled)) {
                $this->cache($parentPath, $this->compile(file_get_contents($parentPath)));
            }
            ob_start();
            include $parentCompiled;
            $output = ltrim(ob_get_clean());
        } else {
            $output = ltrim(ob_get_clean());
        }

        // clear errors after render
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

    protected function getCompiledPath(string $path): string
    {
        return $this->cachePath . '/' . md5($path) . '.php';
    }

    protected function isExpired(string $path, string $compiled): bool
    {
        if (!file_exists($compiled)) {
            return true;
        }
        return filemtime($path) > filemtime($compiled);
    }

    protected function cache(string $path, string $contents): void
    {
        if (!is_dir(dirname($this->getCompiledPath($path)))) {
            mkdir(dirname($this->getCompiledPath($path)), 0755, true);
        }
        file_put_contents($this->getCompiledPath($path), $contents);
    }
}
