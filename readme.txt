# AI Translator for WordPress

AI Translator is a powerful WordPress plugin that enables automatic content translation using OpenAI's GPT models. It provides seamless integration with WPML and Advanced Custom Fields (ACF) for comprehensive content translation management.

## Features

- OpenAI-powered content translation
- WPML integration
- ACF fields support
- Bulk translation capabilities
- Translation memory
- Quality assurance checks
- Translation queue management
- Custom post types support
- Multilingual support

## Requirements

- PHP 7.4 or higher
- WordPress 5.8 or higher
- WPML plugin
- Advanced Custom Fields (ACF) plugin
- OpenAI API key

## Installation

1. Upload the plugin files to the `/wp-content/plugins/ai-translator` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your OpenAI API key in the plugin settings
4. Set up your desired source and target languages

## Configuration

### API Settings

1. Go to AI Translator > Settings
2. Enter your OpenAI API key
3. Select your preferred API model (GPT-4 recommended)

### Language Settings

1. Select your source language (default: French)
2. Choose target languages (English, Arabic, Spanish available)
3. Configure post types for translation

### Advanced Settings

- Batch size: Number of posts to process in each queue run (1-20)
- Translation memory: Enable/disable translation memory feature
- Auto-translation: Configure automatic translation triggers
- Quality checks: Set up validation rules

## Usage

### Single Post Translation

1. Go to AI Translator > Posts
2. Click "Translate" next to the desired post
3. Select target language(s)
4. Click "Start Translation"

### Bulk Translation

1. Select multiple posts using checkboxes
2. Click "Bulk Translate"
3. Choose target languages
4. Confirm the operation

### Translation Management

- Monitor translation status in the main dashboard
- Review and edit translations
- Use the "Rephrase" feature for alternative translations
- Track translation history

## Development

### Setup Development Environment

```bash
# Clone the repository
git clone https://your-repository/ai-translator.git

# Install dependencies
composer install

# Run tests
composer test

# Check coding standards
composer phpcs
```

### Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This project is licensed under the GPL v2 or later - see the [LICENSE](LICENSE) file for details.

## Support

For support, please visit our [support forum](https://your-domain.com/support) or create an issue in the GitHub repository.

## Credits

- Built with [OpenAI API](https://openai.com/blog/openai-api)
- Integrates with [WPML](https://wpml.org/)
- Supports [Advanced Custom Fields](https://www.advancedcustomfields.com/)

## Changelog

### 1.0.0
- Initial release
- Basic translation functionality
- WPML integration
- ACF support
- Bulk translation feature
- Translation queue system