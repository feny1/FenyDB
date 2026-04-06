# FenyDB - Simple JSON File-Based Database

FenyDB is a lightweight, high-performance (for small to medium datasets), file-based JSON database system written in PHP. It uses a structured directory system and inverted indexes to provide fast lookup capabilities without requiring a dedicated database server like MySQL or PostgreSQL.

## Features

- **Zero Configuration**: No database server needed. Just point to a directory.
- **Relational-ish**: Supports tables (directories) and columns (defined in `structure.json`).
- **Indexed Lookups**: Automatically creates and maintains indexes for faster searching on specific columns.
- **Human Readable**: Data is stored in standard JSON files, making it easy to debug or migrate.
- **Simple API**: Easy methods for `insert`, `find`, `findById`, and `getAll`.

## Project Structure

```text
/
├── data/               # Default database directory
│   └── [table_name]/   # Each directory represents a table
│       ├── [id].json   # Individual records stored as separate JSON files
│       ├── index/      # Inverted indexes for columns
│       └── structure.json # Definition of column types
├── FenyDB.php          # The core database class
├── login.php           # Implementation example - Login page
├── index.php           # Implementation example - Dashboard
└── test.php            # Quick test script
```

## API Documentation

### Initialization

```php
require_once 'FenyDB.php';
$db = new FenyDB('data');
```

### Table Management

```php
// Create a table
$db->createTable('users');

// Define columns (indexes)
// non_indexed_types = ['image']
$db->createColumn('users', 'username', 'string');
$db->createColumn('users', 'email', 'string');
$db->createColumn('users', 'password', 'string');
```

### Data Operations

#### Insert Record
```php
$userId = $db->insert('users', [
    'username' => 'johndoe',
    'email' => 'john@example.com',
    'password' => 'secret123'
]);
```

#### Find by ID
```php
$user = $db->findById('users', 1);
```

#### Search by Column (Indexed)
```php
$userIds = $db->find('users', 'username', 'johndoe');
if (!empty($userIds)) {
    $user = $db->findById('users', $userIds[0]);
}
```

#### Get All Records
```php
$allUsers = $db->getAll('users');
```

## Setup & Running the Example

1. **Install PHP**: Ensure you have PHP installed on your system.
2. **Launch Server**:
   ```bash
   php -S localhost:5050
   ```
3. **Access**:
   - Open `http://localhost:5050/login.php` to login.
   - Default credentials depend on the data in your `data/users` folder.

## License

MIT License
