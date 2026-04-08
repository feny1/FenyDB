# FenyDB - Lightweight JSON File-Based Database

FenyDB is a simple, high-performance flat-file JSON database engine for PHP. Ideal for small projects, prototypes, or applications that need a no-SQL solution without the overhead of a dedicated database server.

## ✨ Key Features

- **Zero Dependency**: Pure PHP, no extra extensions or servers required.
- **Fast Lookups**: Uses inverted indexing for O(1) searches on specific columns.
- **Human Readable**: Transparent storage in standard JSON files.
- **Automatic Metadata**: Handles `id`, `created_at`, and `updated_at` automatically.
- **Simple Migration**: Moving data is as easy as copying folders.

---

## 🚀 Installation & Initialization

Simply include the `FenyDB.php` file in your project.

```php
require_once 'FenyDB.php';

// Initialize the database in a specific directory
$db = new FenyDB('my_database');
```

---

## 📋 API Reference & Examples

### 1. Table & Database Management

Manage your database structure easily.

```php
// Create a new table
$db->createTable('users');

// Drop a table (deletes all data)
$db->dropTable('old_logs');

// Delete the entire database directory
$db->dropDatabase();
```

### 2. Column & Index Definition

Indexing is crucial for performance. You can define which columns should be indexed.

```php
// Parameters: (tableName, columnName, type, is_indexed)
$db->createColumn('users', 'username', 'string', true);
$db->createColumn('users', 'email', 'string', true);
$db->createColumn('users', 'bio', 'text', false); // No index for bio
$db->createColumn('users', 'profile_pic', 'image', false); // Arrays and images are not indexable
```

### 3. Inserting Data

Data is sanitized according to the structure defined by your columns.

```php
$userId = $db->insert('users', [
    'username' => 'antigravity',
    'email' => 'ai@example.com',
    'bio' => 'Coding assistant extraordinaire',
    'extra_field' => 'This will be ignored' // Only defined columns are saved
]);

echo "Created user with ID: " . $userId;
```

### 4. Reading Data

Retrieve data by ID or fetch all records.

```php
// Fetch a single record by its ID
$user = $db->findById('users', 1);

// Fetch all records from a table
$allUsers = $db->getAll('users');
```

### 5. Querying (Indexed Search)

Search for records efficiently using indexes.

```php
// find() returns an array of IDs matching the value
$ids = $db->find('users', 'username', 'antigravity');

if (!empty($ids)) {
    $user = $db->findById('users', $ids[0]);
}
```

### 6. Updating Records

Updates are handled by ID. Indexes are automatically updated if the value changes.

```php
$db->update('users', 1, [
    'username' => 'antigravity_pro',
    'email' => 'ai@example.com',
    'bio' => 'Upgraded coding assistant'
]);
```

### 7. Deleting Records

Deleting a record also cleans up related entries in the indexes.

```php
$db->delete('users', 1);
```

---

## 🏆 Full Example Scenario

Here is a complete workflow for managing a simple blog system.

```php
require_once 'FenyDB.php';
$db = new FenyDB('blog_data');

// Setup
$db->createTable('posts');
$db->createColumn('posts', 'title', 'string', true);
$db->createColumn('posts', 'author', 'string', true);
$db->createColumn('posts', 'content', 'text', false);

// Create
$postId = $db->insert('posts', [
    'title' => 'The Future of AI',
    'author' => 'Alice',
    'content' => 'AI is evolving rapidly...'
]);

// Search
$authoredByAlice = $db->find('posts', 'author', 'Alice');

// Display
foreach ($authoredByAlice as $id) {
    $post = $db->findById('posts', $id);
    echo "Title: " . $post['title'] . "\n";
}

// Update
$db->update('posts', $postId, [
    'title' => 'The Future of AI (Updated)',
    'author' => 'Alice',
    'content' => 'Revised content.'
]);

// Cleanup
// $db->dropDatabase();
```

---

## 📂 Internal Directory Structure

FenyDB maintains the following structure for efficiency:

```text
/my_database
  /posts
    1.json           # Record data
    2.json           # Record data
    structure.json   # Column definitions & types
    /index
      title.json     # Inverted index for 'title'
      author.json    # Inverted index for 'author'
```

---

## ⚖️ License

MIT License. Free to use and modify.
