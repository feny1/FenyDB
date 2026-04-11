# 🧬 FenyDB - High Performance, Scalable JSON Database

FenyDB is a next-generation, lightweight flat-file JSON database engine for PHP. Designed for developers who need the simplicity of JSON storage with the scalability of a professional database structure. 

This "Fresh Version" introduces a **Scalable Fan-out Indexing Architecture** and **Memory-Optimized Data Streaming**.

## ✨ Key Features

- **🚀 Scalable Indexing**: Uses a 4-level deep hierarchical hashing structure (Fan-out) to prevent directory congestion and maintain speed with millions of records.
- **⚡ Memory Efficient**: `getAll()` uses PHP Generators to stream data, allowing you to process massive datasets without hitting memory limits.
- **💎 Zero Dependency**: Pure PHP. No extensions, no SQL servers, no configuration.
- **🛠️ Self-Managing Metadata**: Automatic handling of `id`, `created_at`, and `updated_at` timestamps.
- **🔒 Thread Safe**: Built-in file locking for sequence management to ensure data integrity during concurrent inserts.

---

## 🚀 Quick Start

### 1. Initialization
```php
require_once 'FenyDB.php';

// Initialize the database in your storage directory
$db = new FenyDB('storage/app_data');
```

### 2. Define Your Architecture
Define tables and columns. FenyDB supports both indexed and non-indexed columns.

```php
// Create a table
$db->createTable('users');

// Define columns: (table, name, type, is_indexed)
$db->createColumn('users', 'username', 'string', true);
$db->createColumn('users', 'email', 'string', true);
$db->createColumn('users', 'bio', 'text', false); // Non-indexed
```

> [!TIP]
> **Indexing Strategy**: Mark frequently searched columns as `is_indexed = true`. FenyDB's new hierarchical structure makes these searches O(1) even at scale.

---

## 📋 API Reference

### ➕ Inserting Data
Data is automatically sanitized to match your table's structure.

```php
$userId = $db->insert('users', [
    'username' => 'antigravity',
    'email' => 'ai@feny.dev',
    'bio' => 'Building the future of coding.'
]);
```

### 🔍 querying & Searching
Search returns an array of matching IDs.

```php
// Fast lookup using hierarchical index
$ids = $db->find('users', 'username', 'antigravity');

if (!empty($ids)) {
    $user = $db->findById('users', $ids[0]);
    echo "Welcome back, " . $user['username'];
}
```

### 📦 Streaming Data
Process large datasets efficiently with Generators.

```php
// getAll() retrieves data one by one to save memory
foreach ($db->getAll('users') as $user) {
    echo "Processing: " . $user['id'] . "\n";
}
```

> [!NOTE]
> **Performance Note**: `getAll()` no longer returns a standard array. It returns a `Generator`, so you must iterate over it using a loop.

### 🔄 Updates & Deletions
```php
// Update by ID
$db->update('users', 1, ['username' => 'feny_user']);

// Delete by ID
$db->delete('users', 1);
```

---

## 📂 Scalable Directory Structure

FenyDB organizes data using a sophisticated "Fan-out" strategy to maintain filesystem performance:

```text
/storage/app_data
  /users
    /rows
      1.json              # Direct record access
      ...
    /metadata
      structure.json      # Column definitions
      sequence.json       # Auto-increment lock
    /index
      /username
        /a1               # Prefix level 1
          /b2             # Prefix level 2
            /c3           # Prefix level 3
              /d4         # Prefix level 4
                hash.json # Shared index file
```

---

## ⚖️ License

MIT License. Crafted with ❤️ for PHP Developers.

