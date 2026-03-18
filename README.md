# Angeo LLMs.txt Generator for Magento 2

**AI-Optimized LLMs.txt Generator for Magento 2 Stores | Multi-Store & Cron Support**

[![Packagist Version](https://img.shields.io/packagist/v/angeo/module-llms-txt.svg)](https://packagist.org/packages/angeo/module-llms-txt)
[![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)

---

## 🚀 Overview

Angeo LLMs.txt is a Magento 2 module that automatically generates **AI-friendly `llms.txt` files** and **structured `llms.ljson`files** for your eCommerce store. This module helps AI systems like ChatGPT, Claude, Gemini, and other large language models (LLMs) understand your site structure and content for **better indexing, AI recommendations, and enhanced visibility**.

Key features:

- Generates **structured `llms.txt`** and **structured `llms.ljson`** files for products, categories, CMS pages, and stores.
- Supports **multi-store and multi-language Magento 2 installations**.
- Manual generation from **Magento Admin panel**.
- **Cron-enabled automation** for scheduled updates.

> `llms.txt` is a new, AI-focused standard similar to `robots.txt` or `sitemap.xml`, designed to improve AI visibility and context understanding for your store content.

---

## 📦 Installation

Install via Composer in your Magento 2 root:

```bash
composer require angeo/module-llms-txt
bin/magento setup:upgrade
bin/magento cache:flush
```

---

## ⚙️ Configuration

After installation, you can generate LLMS.txt and LLMS.jsonl files:

```
Stores → Configuration → General → Angeo → LLMS
```

Options include:

- Manual generation from the backend

---

## 📆 Cron Automation

The module supports automatic scheduled generation via Magento cron. Ensure your server cron jobs are configured:

```bash
bin/magento cron:run
```

---

## 🧾 llms.txt Structure

The generated file typically includes:

- Store name and description
- Key product categories
- Product URLs
- CMS pages

**Example:**

```
# Store: My Magento Store

## STORE
Name: TEST Store
URL: https://teststore.com/
Currency: USD

### CATEGORIES ###
Category ID: 3
Name: All products
Parent ID: 2
URL: https://teststore.com/all-products
Description: test description

### PRODUCTS ###
SKU: test
Name: test
Price: 100.000000
URL: https://teststore.com/test
Short Description: test short description
Description: test description

## CMS PAGES

TYPE: PAGE
TITLE: 404 Not Found
URL: https://teststore.com/no-route
CONTENT:  The page you requested was not found, and we have a fine guess why. If you typed the URL directly, please make sure the spelling is correct. If you clicked on a link to get here, the link is outdated. What can you do? Have no fear, help is near! There are many ways you can get back on track with Magento Store. Go back to the previous page. Use the search bar at the top of the page to search for your products. Follow these links to get you back on track!Store Home | My Account 

TYPE: PAGE
TITLE: Home page
URL: https://teststore.com/home
CONTENT: CMS homepage content goes here. 
```

This helps AI systems **understand your store context and product catalog** for improved recommendations, search results, and AI-driven features.

---

## 📈 Benefits

- Improve **AI indexing and recommendations**.
- Provide structured content for LLMs.
- Fully **Magento 2 multi-store and multi-language compatible**.
- Easy manual or automated updates via cron or backend.
- Open-source, MIT-licensed for easy adoption and modification.

---

## 🧪 Testing

The module includes unit tests to ensure:

- Correct `llms.txt` generation
- Correct `llms.ljson` generation

---

## 📄 License

This module is released under the **MIT License**.

---

**Keywords for SEO / discoverability:** Magento 2, LLMs.txt, AI-friendly store file, AI optimization, AI recommendations, large language models, ChatGPT, Claude, Gemini, multi-store Magento, cron generation, Magento 2 AI module, AI content indexing.
