<?php

namespace SC_AI\ContentGenerator\Models;

defined( 'ABSPATH' ) || exit;

class Question {
    public int $id;
    public string $title;
    public string $correct_answer;
    public string $explanation;
    public array $options;
    public string $category_name;
    public string $exam_name;
    public string $existing_content;

    public function __construct(
        int $id,
        string $title,
        string $correct_answer,
        string $explanation,
        array $options,
        string $category_name = '',
        string $exam_name = '',
        string $existing_content = ''
    ) {
        $this->id = $id;
        $this->title = $title;
        $this->correct_answer = $correct_answer;
        $this->explanation = $explanation;
        $this->options = $options;
        $this->category_name = $category_name;
        $this->exam_name = $exam_name;
        $this->existing_content = $existing_content;
    }

    public static function fromArray( array $data ): self {
        return new self(
            $data['id'] ?? 0,
            $data['title'] ?? '',
            $data['correct_answer'] ?? '',
            $data['explanation'] ?? '',
            $data['options'] ?? [],
            $data['category_name'] ?? '',
            $data['exam_name'] ?? '',
            $data['existing_content'] ?? ''
        );
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'correct_answer' => $this->correct_answer,
            'explanation' => $this->explanation,
            'options' => $this->options,
            'category_name' => $this->category_name,
            'exam_name' => $this->exam_name,
            'existing_content' => $this->existing_content,
        ];
    }
}
