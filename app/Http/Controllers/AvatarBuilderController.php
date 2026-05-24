<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AvatarBuilderController extends Controller
{
    private function configPath(): string
    {
        return resource_path('js/Composables/useAvatarConfig.js');
    }

    private function avatarsDir(): string
    {
        return public_path('avatars');
    }

    private function shopPath(): string
    {
        return storage_path('app/avatar-shop.json');
    }

    public function show()
    {
        return view('builder');
    }

    public function config()
    {
        $path = $this->configPath();

        if (! file_exists($path)) {
            return response()->json(['error' => 'Config file not found'], 500);
        }

        return response(file_get_contents($path), 200, ['Content-Type' => 'text/plain']);
    }

    public function files()
    {
        $dir = $this->avatarsDir();
        $files = [];

        if (is_dir($dir)) {
            foreach (scandir($dir) as $f) {
                if (str_ends_with($f, '.png')) {
                    $files[] = $f;
                }
            }
        }

        sort($files);

        return response()->json($files);
    }

    public function sync(Request $request)
    {
        $data = $request->validate([
            'alignment' => 'required|array',
            'items' => 'nullable|array',
            'gender' => 'nullable|array',
            'paid' => 'nullable|array',
            'costumes' => 'nullable|array',
        ]);

        $newAlignment = $data['alignment'];
        $newItems = $data['items'] ?? null;
        $newGender = $data['gender'] ?? null;

        $configPath = $this->configPath();
        $content = file_get_contents($configPath);

        $existingAlignment = $this->extractObj($content, 'AVATAR_ALIGNMENT') ?? [];
        $existingItems = $this->extractObj($content, 'AVATAR_ITEMS') ?? [];

        // Merge alignment
        $mergedAlignment = [];
        $mergedItems = [];

        foreach (['eyes', 'hair', 'beard'] as $layer) {
            $merged = array_merge(
                $existingAlignment[$layer] ?? [],
                $newAlignment[$layer] ?? []
            );
            // Filter out files that don't exist on disk
            $mergedAlignment[$layer] = [];
            foreach ($merged as $file => $pos) {
                if (file_exists($this->avatarsDir().'/'.$file)) {
                    $mergedAlignment[$layer][$file] = $pos;
                }
            }

            // Merge items: keep existing, add new, deduplicate, skip missing files
            $existingList = $existingItems[$layer] ?? [];
            $newList = $newItems[$layer] ?? [];
            $seen = [];
            $mergedItems[$layer] = [];
            foreach (array_merge($existingList, $newList) as $item) {
                if (! isset($seen[$item]) && file_exists($this->avatarsDir().'/'.$item)) {
                    $seen[$item] = true;
                    $mergedItems[$layer][] = $item;
                }
            }
        }

        // Replace AVATAR_ALIGNMENT
        $content = $this->replaceExportConst($content, 'AVATAR_ALIGNMENT', $mergedAlignment);

        // Replace AVATAR_ITEMS
        $content = $this->replaceExportConst($content, 'AVATAR_ITEMS', $mergedItems);

        // Replace AVATAR_GENDER
        if ($newGender) {
            $existingGender = $this->extractObj($content, 'AVATAR_GENDER') ?? [];
            $mergedGender = [];
            foreach (['eyes', 'hair', 'beard'] as $layer) {
                $mergedGender[$layer] = array_merge(
                    $existingGender[$layer] ?? [],
                    $newGender[$layer] ?? []
                );
            }
            $content = $this->replaceExportConst($content, 'AVATAR_GENDER', $mergedGender);
        }

        // Replace AVATAR_PAID
        $paid = $data['paid'] ?? [];
        if (empty($paid)) {
            $paid = new \stdClass;
        }
        $content = $this->replaceExportConst($content, 'AVATAR_PAID', (array) $paid);

        // Replace AVATAR_COSTUMES with auto-generated IDs
        $costumes = array_map(function ($c) {
            $id = preg_replace('/[^a-z0-9]+/', '_', strtolower($c['name']));
            $id = trim($id, '_');

            return array_merge(['id' => $id], $c);
        }, $data['costumes'] ?? []);

        $content = $this->replaceExportConstArray($content, 'AVATAR_COSTUMES', $costumes);

        file_put_contents($configPath, $content);

        // Write avatar-shop.json
        file_put_contents($this->shopPath(), json_encode([
            'paid' => $paid,
            'costumes' => $costumes,
        ], JSON_PRETTY_PRINT));

        return response()->json(['status' => 'synced', 'path' => $configPath]);
    }

    public function upload(Request $request)
    {
        $data = $request->validate([
            'filename' => 'required|string',
            'data' => 'required|string',
            'layer' => 'nullable|string',
        ]);

        if (! preg_match('/^data:image\/png;base64,(.+)$/', $data['data'], $matches)) {
            return response()->json(['error' => 'Invalid image data (must be PNG)'], 400);
        }

        $buffer = base64_decode($matches[1]);
        $destPath = $this->avatarsDir().'/'.$data['filename'];
        file_put_contents($destPath, $buffer);

        return response()->json([
            'status' => 'uploaded',
            'filename' => $data['filename'],
            'path' => $destPath,
        ]);
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'filename' => 'required|string',
            'layer' => 'required|string',
        ]);

        $filename = $data['filename'];
        $layer = $data['layer'];
        $baseName = str_replace('.png', '', $filename);
        $toRemove = [$filename];

        $configPath = $this->configPath();
        $content = file_get_contents($configPath);

        $existingAlignment = $this->extractObj($content, 'AVATAR_ALIGNMENT') ?? [];
        $existingItems = $this->extractObj($content, 'AVATAR_ITEMS') ?? [];

        // Remove alignment entries
        if (isset($existingAlignment[$layer][$filename])) {
            unset($existingAlignment[$layer][$filename]);
        }
        foreach (array_keys($existingAlignment[$layer] ?? []) as $key) {
            if (str_starts_with($key, $baseName.'_dup')) {
                unset($existingAlignment[$layer][$key]);
                $toRemove[] = $key;
            }
        }

        // Remove from items list
        if (isset($existingItems[$layer])) {
            $existingItems[$layer] = array_values(array_filter(
                $existingItems[$layer],
                fn ($item) => $item !== $filename && ! str_starts_with($item, $baseName.'_dup')
            ));
        }

        $content = $this->replaceExportConst($content, 'AVATAR_ALIGNMENT', $existingAlignment);
        $content = $this->replaceExportConst($content, 'AVATAR_ITEMS', $existingItems);
        file_put_contents($configPath, $content);

        // Delete physical files
        $deleted = [];
        foreach ($toRemove as $f) {
            $gamePath = $this->avatarsDir().'/'.$f;
            if (file_exists($gamePath)) {
                unlink($gamePath);
                $deleted[] = 'game:'.$f;
            }
        }

        return response()->json([
            'status' => 'removed',
            'filename' => $filename,
            'layer' => $layer,
            'deleted' => $deleted,
        ]);
    }

    private function extractObj(string $content, string $name): ?array
    {
        $marker = "export const {$name} = ";
        $start = strpos($content, $marker);
        if ($start === false) {
            return null;
        }

        $objStart = strpos($content, '{', $start);
        if ($objStart === false) {
            return null;
        }

        $braceCount = 0;
        $objEnd = $objStart;
        for ($i = $objStart; $i < strlen($content); $i++) {
            if ($content[$i] === '{') {
                $braceCount++;
            }
            if ($content[$i] === '}') {
                $braceCount--;
            }
            if ($braceCount === 0) {
                $objEnd = $i + 1;
                break;
            }
        }

        $json = substr($content, $objStart, $objEnd - $objStart);

        return json_decode($json, true);
    }

    private function replaceExportConst(string $content, string $name, array $data): string
    {
        $marker = "export const {$name} = ";
        $idx = strpos($content, $marker);
        if ($idx === false) {
            return $content."\n{$marker}".json_encode($data, JSON_PRETTY_PRINT)."\n";
        }

        $objStart = strpos($content, '{', $idx);
        if ($objStart === false) {
            return $content;
        }

        $braceCount = 0;
        $objEnd = $objStart;
        for ($i = $objStart; $i < strlen($content); $i++) {
            if ($content[$i] === '{') {
                $braceCount++;
            }
            if ($content[$i] === '}') {
                $braceCount--;
            }
            if ($braceCount === 0) {
                $objEnd = $i + 1;
                break;
            }
        }

        $formatted = collect(explode("\n", json_encode($data, JSON_PRETTY_PRINT)))
            ->map(fn ($line, $i) => $i === 0 ? $line : '  '.$line)
            ->implode("\n");

        return substr($content, 0, $idx + strlen($marker)).$formatted.substr($content, $objEnd);
    }

    private function replaceExportConstArray(string $content, string $name, array $data): string
    {
        $marker = "export const {$name} = ";
        $idx = strpos($content, $marker);
        if ($idx === false) {
            return $content."\n{$marker}".json_encode($data, JSON_PRETTY_PRINT)."\n";
        }

        $eqPos = strpos($content, '=', $idx);
        if ($eqPos === false) {
            return $content;
        }

        $arrStart = strpos($content, '[', $eqPos);
        if ($arrStart === false) {
            return $content;
        }

        $depth = 0;
        $arrEnd = $arrStart;
        for ($i = $arrStart; $i < strlen($content); $i++) {
            if ($content[$i] === '[') {
                $depth++;
            }
            if ($content[$i] === ']') {
                $depth--;
            }
            if ($depth === 0) {
                $arrEnd = $i + 1;
                break;
            }
        }

        $formatted = json_encode($data, JSON_PRETTY_PRINT);

        return substr($content, 0, $eqPos + 2).$formatted.substr($content, $arrEnd);
    }
}
