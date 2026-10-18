#!/usr/bin/env python3
"""
Enterprise Web Scraper for AI Chat Board.
Extracts clean, structured text and metadata from business websites.
Uses trafilatura/BeautifulSoup when installed, with a reliable standard-library fallback.
"""

import sys
import json
import re
import urllib.request
import urllib.error
import urllib.parse
from html.parser import HTMLParser
from datetime import datetime, timezone

# Common user agent to prevent bot rejection
USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"

# Timeout in seconds
TIMEOUT = 12

class CleanTextExtractor(HTMLParser):
    """Fallback text extractor using Python standard library."""
    def __init__(self):
        super().__init__()
        self.reset()
        self.fed = []
        self.skip_tags = {'script', 'style', 'noscript', 'nav', 'footer', 'header', 'svg', 'aside', 'form'}
        self.current_skip = 0
        self.title = ""
        self.in_title = False
        self.meta_desc = ""
        self.links = set()

    def handle_starttag(self, tag, attrs):
        tag_lower = tag.lower()
        if tag_lower in self.skip_tags:
            self.current_skip += 1
        elif tag_lower == 'title':
            self.in_title = True
        elif tag_lower == 'meta':
            attrs_dict = dict(attrs)
            if attrs_dict.get('name', '').lower() == 'description':
                self.meta_desc = attrs_dict.get('content', '')
            elif attrs_dict.get('property', '').lower() == 'og:description' and not self.meta_desc:
                self.meta_desc = attrs_dict.get('content', '')
        elif tag_lower == 'a':
            attrs_dict = dict(attrs)
            href = attrs_dict.get('href')
            if href:
                self.links.add(href)

    def handle_endtag(self, tag):
        tag_lower = tag.lower()
        if tag_lower in self.skip_tags and self.current_skip > 0:
            self.current_skip -= 1
        elif tag_lower == 'title':
            self.in_title = False

    def handle_data(self, data):
        if self.in_title:
            self.title += data
        elif self.current_skip == 0:
            stripped = data.strip()
            if stripped:
                self.fed.append(stripped)

    def get_text(self):
        # Join words and normalize excessive spaces/newlines
        raw = " ".join(self.fed)
        raw = re.sub(r'\s+', ' ', raw)
        return raw.strip()


def fetch_html(url: str) -> tuple[str, str]:
    """Fetch HTML content from a URL safely."""
    req = urllib.request.Request(
        url,
        headers={"User-Agent": USER_AGENT, "Accept": "text/html,application/xhtml+xml"}
    )
    # Ignore SSL verification errors if needed for local test sites
    import ssl
    ctx = ssl.create_default_context()
    ctx.check_hostname = False
    ctx.verify_mode = ssl.CERT_NONE

    with urllib.request.urlopen(req, timeout=TIMEOUT, context=ctx) as response:
        content_type = response.headers.get_content_type()
        encoding = response.headers.get_content_charset() or 'utf-8'
        html = response.read().decode(encoding, errors='replace')
        final_url = response.geturl()
        return html, final_url


def extract_page_data(html: str, url: str) -> dict:
    """Extract page title, text content, and internal links."""
    # Attempt extraction via trafilatura if installed
    clean_text = None
    try:
        import trafilatura
        extracted = trafilatura.extract(html, include_links=False, include_images=False)
        if extracted and len(extracted.strip()) > 50:
            clean_text = extracted.strip()
    except ImportError:
        pass

    # Use HTMLParser for title, links, and text fallback
    parser = CleanTextExtractor()
    try:
        parser.feed(html)
    except Exception:
        pass

    if not clean_text:
        clean_text = parser.get_text()

    # Fallback title from URL if missing
    title = parser.title.strip()
    if not title:
        title = urllib.parse.urlparse(url).netloc

    return {
        "url": url,
        "page_title": title[:250],
        "meta_description": parser.meta_desc[:500],
        "clean_text": clean_text,
        "links": list(parser.links)
    }


def find_key_subpages(base_url: str, links: list[str], max_pages: int = 3) -> list[str]:
    """Identify relevant internal links like /about, /services, /contact, /faq."""
    parsed_base = urllib.parse.urlparse(base_url)
    base_domain = parsed_base.netloc.lower()

    keywords = ['about', 'service', 'product', 'contact', 'pricing', 'faq', 'info', 'feature', 'help']
    candidates = []

    for link in links:
        absolute_url = urllib.parse.urljoin(base_url, link)
        parsed_link = urllib.parse.urlparse(absolute_url)

        # Must be same domain and http(s)
        if parsed_link.netloc.lower() == base_domain and parsed_link.scheme in ['http', 'https']:
            path = parsed_link.path.lower()
            # Ignore binary assets
            if any(path.endswith(ext) for ext in ['.jpg', '.png', '.pdf', '.zip', '.css', '.js']):
                continue

            for kw in keywords:
                if kw in path and absolute_url not in candidates and absolute_url != base_url:
                    candidates.append(absolute_url)
                    break
        if len(candidates) >= max_pages:
            break

    return candidates


def scrape_website(target_url: str, max_subpages: int = 2) -> dict:
    """Orchestrate scraping the target website and relevant subpages."""
    if not target_url.startswith(('http://', 'https://')):
        target_url = 'https://' + target_url

    pages = []
    scraped_urls = set()

    # 1. Scrape root/landing page
    try:
        html, final_url = fetch_html(target_url)
        page_info = extract_page_data(html, final_url)
        scraped_urls.add(final_url)
        scraped_urls.add(target_url)
        now_iso = datetime.now(timezone.utc).isoformat()

        pages.append({
            "url": final_url,
            "page_title": page_info["page_title"],
            "clean_text": page_info["clean_text"],
            "scraped_at": now_iso
        })

        # 2. Discover key subpages
        subpages = find_key_subpages(final_url, page_info["links"], max_pages=max_subpages)
        for sub_url in subpages:
            if sub_url not in scraped_urls:
                scraped_urls.add(sub_url)
                try:
                    sub_html, sub_final = fetch_html(sub_url)
                    sub_info = extract_page_data(sub_html, sub_final)
                    pages.append({
                        "url": sub_final,
                        "page_title": sub_info["page_title"],
                        "clean_text": sub_info["clean_text"],
                        "scraped_at": datetime.now(timezone.utc).isoformat()
                    })
                except Exception:
                    continue

        combined_text = "\n\n---\n\n".join([
            f"### Page: {p['page_title']} ({p['url']})\n{p['clean_text']}"
            for p in pages
        ])

        parsed_netloc = urllib.parse.urlparse(final_url).netloc
        business_name = page_info["page_title"].split('-')[0].split('|')[0].strip()
        if not business_name or len(business_name) > 60:
            business_name = parsed_netloc.replace('www.', '').capitalize()

        favicon_url = urllib.parse.urljoin(final_url, "/favicon.ico")

        return {
            "status": "success",
            "business_name": business_name,
            "root_url": final_url,
            "pages_crawled": len(pages),
            "combined_text": combined_text[:25000], # safe token budget
            "meta": {
                "title": page_info["page_title"],
                "description": page_info.get("meta_description", ""),
                "favicon": favicon_url,
                "domain": parsed_netloc,
            },
            "pages": pages
        }

    except Exception as e:
        return {
            "status": "error",
            "message": f"Failed to scrape {target_url}: {str(e)}",
            "root_url": target_url,
            "pages_crawled": 0,
            "combined_text": "",
            "pages": []
        }


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"status": "error", "message": "Target URL required as first argument."}))
        sys.exit(1)

    target_url = sys.argv[1]
    result = scrape_website(target_url)
    print(json.dumps(result, ensure_ascii=False))


if __name__ == "__main__":
    main()
