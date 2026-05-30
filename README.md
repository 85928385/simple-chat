# Simple Chat

A lightweight, zero-configuration real-time chat application built with PHP. Forked from [Stephan Soller's Simple Chat v2.0.2](http://arkanis.de/projects/simple-chat/), enhanced with emoji support and Chinese localization.

## Features

- **Zero Setup** — No database required. Just upload `index.php` to any PHP-enabled web server.
- **Real-Time Messaging** — Uses AJAX polling (every 2 seconds) to deliver messages in real time.
- **Emoji Picker** — Built-in emoji panel with 50+ common emojis for expressive chatting.
- **Auto-Scroll** — Automatically scrolls to new messages when you're near the bottom.
- **Pending Messages** — Shows your message immediately before server confirmation.
- **Message History** — Buffers the latest 1,000 messages in `messages.json`.
- **Chat Logging** — Optionally appends all messages to `chatlog.txt`.
- **Chinese UI** — Full Chinese localization for interfaces and timestamps.

## Requirements

- PHP 7.0+ (or any version with JSON and file locking support)
- A web server (Apache, Nginx, IIS, etc.)
- Write permissions for the web server on the installation directory

## Installation

1. Copy `index.php` to your web server directory.
2. Ensure the web server has read and write permissions for the directory.
3. Access `index.php` through your browser.

That's it. No database, no configuration file, no dependencies to install.

The file `messages.json` will be created automatically on the first message.

If you want to enable chat logging, set `$enable_chatlog = true;` in `index.php`. All messages will be appended to `chatlog.txt`.

## Usage

1. Enter your nickname in the "昵称" field (defaults to "匿名用户").
2. Type your message in the "输入消息内容..." field.
3. Click the smiley button 😊 to open the emoji picker.
4. Click the "发送" button or press Enter to send.

## Configuration

You can adjust the following settings at the top of `index.php`:

| Variable | Default | Description |
|----------|---------|-------------|
| `$messages_buffer_size` | 1000 | Number of latest messages kept in the buffer |
| `$enable_chatlog` | `true` | When enabled, appends all messages to `chatlog.txt` |

## File Structure

```
simple-chat/
├── index.php        # Main chat application (single file)
├── messages.json    # Message buffer (auto-created)
├── chatlog.txt      # Chat log file (if enabled)
├── README.md        # This file
├── LICENSE          # MIT License
└── CHANGELOG.md     # Version history
```

## License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

The original Simple Chat v2.0.2 by Stephan Soller is licensed under a BSD-like license.
