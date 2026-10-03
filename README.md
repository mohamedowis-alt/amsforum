# amsforum.com

Code for the Amsterdam Forum website. The live site installs it from the admin (**Updates → Update website**).

- `defaults/content.json` — the default page content. On update it is merged into the live content: anything edited in the admin is kept; new sections are added.
- The root `.htaccess`, `data/` (content, password, submissions) and `uploads/` on the server are never overwritten.
