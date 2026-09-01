<?php

namespace SC_AI\ContentGenerator\Services\Topic;

defined( 'ABSPATH' ) || exit;

class TopicCoveragePlanner {
    private array $default_blueprints = [
        'javascript' => [
            'Variables, Scopes & Closures',
            'Functions, Arrow Functions & this binding',
            'Objects, Prototypes & Classes',
            'Arrays, Iterators & Higher-Order Functions',
            'Asynchronous JavaScript, Promises & Async/Await',
            'Event Loop, Microtasks & Macrotasks',
            'DOM Manipulation & Event Handling',
            'Error Handling & Debugging',
            'Modules (ESM & CommonJS)',
            'Performance Optimization & Memory Management',
        ],
        'python' => [
            'Basic Syntax, Data Types & Control Flow',
            'Functions, Decorators & Generators',
            'Object-Oriented Programming & Dunder Methods',
            'List Comprehensions & Functional Tools',
            'Exception Handling & Context Managers (with)',
            'File I/O, Serialization (JSON, Pickle)',
            'Standard Library (itertools, collections, os, sys)',
            'Virtual Environments & Package Management',
            'Concurrency, Threading & Asyncio',
            'Data Structures & Algorithm Complexity',
        ],
        'sql' => [
            'Basic Queries, SELECT, WHERE & Filtering',
            'JOINs (INNER, LEFT, RIGHT, FULL, CROSS)',
            'Aggregate Functions & GROUP BY / HAVING',
            'Subqueries & Correlated Subqueries',
            'Window Functions (ROW_NUMBER, RANK, LEAD, LAG)',
            'Common Table Expressions (CTEs & Recursive CTEs)',
            'Data Modification (INSERT, UPDATE, DELETE, MERGE)',
            'Table Design, Indexes & Constraints',
            'Transactions, ACID Properties & Isolation Levels',
            'Query Optimization & Execution Plans',
        ],
        'react' => [
            'JSX Syntax & Component Architecture',
            'State & Props Management',
            'React Hooks (useState, useEffect, useMemo, useCallback)',
            'Custom Hooks & Reusable Logic',
            'Context API & Global State',
            'Component Lifecycle & Side Effects',
            'Forms, Controlled vs Uncontrolled Components',
            'Performance Optimization & React.memo',
            'Routing & Code Splitting',
            'Error Boundaries & Testing Fundamentals',
        ],
        'php' => [
            'Variables, Superglobals & Types',
            'Functions, Type Hinting & Return Types',
            'OOP Principles, Interfaces & Traits',
            'Error & Exception Handling',
            'Arrays, String Manipulation & Regular Expressions',
            'Sessions, Cookies & Authentication',
            'Database Interaction with PDO & Prepared Statements',
            'Security (XSS, CSRF, SQL Injection, Sanitization)',
            'Composer, Namespaces & PSR Standards',
            'REST APIs & JSON Handling',
        ],
        'java' => [
            'Core Syntax, Data Types & Operators',
            'Object-Oriented Programming (Inheritance, Polymorphism)',
            'Collections Framework (List, Set, Map)',
            'Generics & Type Safety',
            'Exception Handling (Checked vs Unchecked)',
            'Multithreading, Concurrency & Synchronization',
            'Streams API & Lambda Expressions',
            'Memory Management & Garbage Collection',
            'File I/O & NIO.2',
            'Design Patterns & JVM Architecture',
        ],
        'aws' => [
            'Cloud Fundamentals & Global Infrastructure',
            'Compute (EC2, Lambda, ECS, Fargate)',
            'Storage (S3, EBS, EFS)',
            'Database (RDS, DynamoDB, Aurora)',
            'Networking (VPC, Subnets, Route 53, CloudFront)',
            'Security, Identity & IAM Policies',
            'Monitoring & Management (CloudWatch, CloudTrail)',
            'Auto Scaling & Elastic Load Balancing',
            'High Availability & Disaster Recovery',
            'Cost Management & Well-Architected Framework',
        ],
        'typescript' => [
            'Basic Types, Type Inference & Type Annotations',
            'Interfaces, Type Aliases & Hybrid Types',
            'Generics & Type Constraints',
            'Union, Intersection & Discriminated Unions',
            'Utility Types (Partial, Required, Readonly, Pick, Record)',
            'Classes, Access Modifiers & Abstract Classes',
            'Enums, Const Assertions & Literal Types',
            'Type Narrowing, Type Guards & Type Assertions',
            'Modules, Namespaces & Declaration Files (.d.ts)',
            'Strict Compiler Options & tsconfig Configuration',
        ],
        'data-science' => [
            'Data Cleaning, Preprocessing & Imputation',
            'Exploratory Data Analysis (EDA) & Feature Engineering',
            'Supervised Learning (Regression & Classification)',
            'Unsupervised Learning (Clustering & Dimensionality Reduction)',
            'Model Evaluation Metrics (ROC-AUC, F1, RMSE, Cross-Validation)',
            'Ensemble Methods (Random Forests, Gradient Boosting, XGBoost)',
            'Neural Networks & Deep Learning Foundations',
            'Time Series Analysis & Forecasting',
            'MLOps, Model Deployment & Pipeline Versioning',
            'Data Ethics, Bias & Interpretability (SHAP, LIME)',
        ],
        'wordpress' => [
            'Core Architecture, Hooks (Actions & Filters) & Execution Flow',
            'Plugin Development & Best Practices',
            'Theme Structure, Template Hierarchy & Child Themes',
            'Custom Post Types, Taxonomies & Custom Fields',
            'Database Interaction, $wpdb & Query Optimization',
            'REST API Endpoints & Custom Controllers',
            'Security Best Practices (Nonces, Sanitization, Escaping)',
            'Shortcodes, Widgets & Gutenberg Block Development',
            'Transients API, Caching & Performance Optimization',
            'User Roles, Capabilities & Authentication',
        ],
        'ai' => [
            'Foundations of Machine Learning & Neural Networks',
            'Transformer Architecture, Attention Mechanisms & LLMs',
            'Prompt Engineering & In-Context Learning',
            'Retrieval-Augmented Generation (RAG) Architecture',
            'Fine-Tuning Techniques (LoRA, QLoRA, PEFT)',
            'Vector Databases, Embeddings & Semantic Search',
            'Agentic AI, Tool Use & Multi-Agent Orchestration',
            'AI Safety, Guardrails, Alignment & Hallucination Mitigation',
            'Model Evaluation, Benchmarking & Quantization',
            'Ethical AI, Privacy & Regulatory Compliance',
        ],
    ];

    /**
     * Get a target topic for the next question batch based on existing distribution
     */
    public function planTopic( int $skill_id, string $skill_slug, string $difficulty ): string {
        $blueprint = $this->getTopicsForSkill( $skill_id, $skill_slug );

        if ( empty( $blueprint ) ) {
            return ucfirst( str_replace( '-', ' ', $skill_slug ) ) . ' Core Principles';
        }

        // Fetch existing topic counts for this skill & difficulty
        $counts = $this->getExistingTopicCounts( $skill_id, $difficulty );

        // Find the topic with the lowest count
        $lowest_topic = $blueprint[0];
        $min_count = PHP_INT_MAX;

        foreach ( $blueprint as $topic ) {
            $c = $counts[ strtolower( $topic ) ] ?? 0;
            if ( $c < $min_count ) {
                $min_count = $c;
                $lowest_topic = $topic;
            }
        }

        return $lowest_topic;
    }

    public function getTopicsForSkill( int $skill_id, string $skill_slug ): array {
        // 1. Primary Source of Truth: Canonical Taxonomy Service
        if ( class_exists( '\SCP_Taxonomy_Service' ) || class_exists( 'SCP_Taxonomy_Service' ) ) {
            try {
                $canonical_context = \SCP_Taxonomy_Service::resolve_legacy_category( $skill_id ?: $skill_slug );
                if ( ! empty( $canonical_context['is_mapped'] ) && ! empty( $canonical_context['skill']['id'] ) ) {
                    $canonical_topics = \SCP_Taxonomy_Service::get_topics( (int) $canonical_context['skill']['id'] );
                    if ( ! empty( $canonical_topics ) && is_array( $canonical_topics ) ) {
                        $topic_names = array_filter( array_column( $canonical_topics, 'name' ) );
                        if ( ! empty( $topic_names ) ) {
                            return array_values( $topic_names );
                        }
                    }
                }
            } catch ( \Throwable $e ) {
                error_log( '[SC AI TopicCoveragePlanner] Canonical topic resolution error: ' . $e->getMessage() );
            }
        }

        // 2. Secondary: Check if term has custom topic blueprint in term meta
        if ( function_exists( 'get_term_meta' ) ) {
            $meta_topics = get_term_meta( $skill_id, 'scp_topic_blueprint', true );
            if ( is_array( $meta_topics ) && ! empty( $meta_topics ) ) {
                return $meta_topics;
            }
        }

        // 3. Fallback: Check default blueprints by slug match
        $slug_clean = strtolower( trim( $skill_slug ) );
        foreach ( $this->default_blueprints as $key => $topics ) {
            if ( strpos( $slug_clean, $key ) !== false ) {
                return $topics;
            }
        }

        // 4. Ultimate Fallback: Generic topics for any skill
        $skill_name = ucfirst( str_replace( '-', ' ', $skill_slug ) );
        return [
            "{$skill_name} Fundamentals & Core Concepts",
            "{$skill_name} Syntax & Standard Operations",
            "{$skill_name} Architecture & Design Patterns",
            "{$skill_name} Practical Applications & Scenarios",
            "{$skill_name} Performance, Security & Best Practices",
            "{$skill_name} Error Handling, Debugging & Edge Cases",
        ];
    }

    private function getExistingTopicCounts( int $skill_id, string $difficulty ): array {
        global $wpdb;

        // Fetch questions belonging to this skill and difficulty
        $results = $wpdb->get_results( $wpdb->prepare( "
            SELECT pm_topic.meta_value as topic, COUNT(*) as count
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
            INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            LEFT JOIN {$wpdb->postmeta} pm_topic ON p.ID = pm_topic.post_id AND pm_topic.meta_key = '_topic'
            LEFT JOIN {$wpdb->postmeta} pm_diff ON p.ID = pm_diff.post_id AND pm_diff.meta_key = '_scp_difficulty'
            WHERE p.post_type = 'scp_question'
            AND p.post_status = 'publish'
            AND tt.term_id = %d
            AND (pm_diff.meta_value = %s OR %s = '')
            AND pm_topic.meta_value IS NOT NULL
            GROUP BY pm_topic.meta_value
        ", $skill_id, $difficulty, $difficulty ) );

        $counts = [];
        if ( is_array( $results ) ) {
            foreach ( $results as $row ) {
                if ( ! empty( $row->topic ) ) {
                    $counts[ strtolower( trim( $row->topic ) ) ] = (int) $row->count;
                }
            }
        }

        return $counts;
    }
}
