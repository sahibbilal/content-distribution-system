# Website / blog API contract

What the content distribution system's **Website** platform calls on your site (bilalmahmood.dev).
In Platforms → Website, set **API URL** to the base (e.g. `https://bilalmahmood.dev/api`) and **Bearer token** to a token your site accepts.

All requests send `Authorization: Bearer <token>` and `Accept: application/json`.
Respond `401` for a bad token. For errors, return JSON with a `message` field (it is shown to the user).

## 1. `GET {base}/me`: test connection
Any authenticated `200` is a success. Optional body: `{ "name": "Bilal" }`.

## 2. `POST {base}/media`: upload one file
`multipart/form-data`: `file` (the file), `type` (`image` | `video` | `audio`).
Respond `201`:
```json
{ "url": "https://bilalmahmood.dev/uploads/abc.jpg" }
```
`url` must be publicly reachable.

## 3. `POST {base}/posts`: create a published post
JSON body:
```json
{
  "title": "First line of the post (max 150 chars)",
  "content": "<p>Sanitized HTML body (title block already removed)</p>",
  "excerpt": "Plain-text summary, up to 200 chars",
  "status": "published",
  "cover_image": "https://bilalmahmood.dev/uploads/abc.jpg",
  "media": [{ "type": "image", "url": "https://bilalmahmood.dev/uploads/abc.jpg" }]
}
```
- `cover_image` is the first uploaded image, or `null`.
- `media` lists every uploaded file (empty array if none). Render video/audio however the blog wants.
- `content` allows only: p, br, strong, em, u, s, ul, ol, li, a, blockquote, h2, h3.

Respond `201`:
```json
{ "id": 42, "url": "https://bilalmahmood.dev/blog/my-post-slug" }
```
`id` is required (a response without it counts as a failure). `url` is shown as the link to the live post.
