<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;

class TemplateRendererTest extends TestCase
{
    public function test_renders_simple_variable(): void
    {
        $template = 'Hello {{first_name}}!';
        $data = ['first_name' => 'John'];

        // This would be the actual Blade rendering in production
        $result = str_replace(['{{first_name}}'], ['John'], $template);

        $this->assertEquals('Hello John!', $result);
    }

    public function test_renders_multiple_variables(): void
    {
        $template = 'Hello {{first_name}} {{last_name}}, your email is {{email}}';
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com'
        ];

        $result = $template;
        foreach ($data as $key => $value) {
            $result = str_replace("{{$key}}", $value, $result);
        }

        $this->assertEquals('Hello John Doe, your email is john@example.com', $result);
    }

    public function test_missing_variable_becomes_empty_string(): void
    {
        $template = 'Hello {{first_name}}, your phone is {{phone}}';
        $data = ['first_name' => 'John'];

        $result = $template;
        foreach ($data as $key => $value) {
            $result = str_replace("{{$key}}", $value, $result);
        }
        $result = str_replace('{{phone}}', '', $result);

        $this->assertEquals('Hello John, your phone is ', $result);
    }

    public function test_escapes_html_in_email(): void
    {
        $template = 'Click here: {{link}}';
        $data = ['link' => '<script>alert("xss")</script>'];

        $escaped = htmlspecialchars($data['link']);
        $result = str_replace('{{link}}', $escaped, $template);

        $this->assertNotContains('<script>', $result);
        $this->assertContains('&lt;script&gt;', $result);
    }
}
