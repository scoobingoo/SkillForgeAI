<?php

// SPDX-License-Identifier: Apache-2.0
// Bộ nạp Prompt có quản lý phiên bản từ thư mục resources/prompts/<task>.v1.md với cơ chế thay thế biến {{var}}

namespace App\Services\Llm;

use RuntimeException;

class PromptLoader
{
    /**
     * Đọc file prompt theo tên tác vụ và chèn các biến {{var}}.
     *
     * @param  string  $task  Tên tác vụ (ví dụ: 'extract-cv', 'extract-jd', 'disambiguate-skill', 'explain-gap', 'interview-question', 'interview-grade')
     * @param  array<string, string>  $variables  Các biến cần thay thế
     */
    public static function load(string $task, array $variables = []): string
    {
        $path = resource_path("prompts/{$task}.v1.md");
        if (! is_file($path)) {
            throw new RuntimeException("Không tìm thấy file prompt: {$path}");
        }

        $content = (string) file_get_contents($path);
        foreach ($variables as $key => $value) {
            $content = str_replace('{{'.$key.'}}', $value, $content);
        }

        return $content;
    }
}
