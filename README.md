# Angeo Magento 2 LLMs.txt Generator

[![Packagist Version](https://img.shields.io/packagist/v/angeo/module-llms-txt.svg)](https://packagist.org/packages/angeo/module-llms-txt)
[![License](https://img.shields.io/packagist/l/angeo/module-llms-txt.svg)](https://packagist.org/packages/angeo/module-llms-txt)

## Overview

**Angeo LLMs.txt Magento 2 Module** is a professional **AI Engine Optimization (AEO) solution** for eCommerce stores.

This module generates **AI-readable `llms.txt` files**, fully optimized for **ChatGPT, Claude, Perplexity AI**, and other AI search engines.  
Supports **multi-store, cron-based generation, and manual CLI execution**, making your Magento 2 store fully **AI-friendly**.

---

## Features

- Automatic `llms.txt` generation for **AI Engine Optimization (AEO)**.
- Multi-store support: `/llms-en.txt`, `/llms-de.txt`, etc.
- Manual generation from backoffice
- Cron generation
- Cron job support for scheduled updates.
- AI-friendly structure including product feeds, categories, CMS pages, and store sections.
---

## Installation

```bash
composer require angeo/module-llms-txt
bin/magento setup:upgrade
bin/magento cache:flush