(function () {
  "use strict";
  var boot = JSON.parse(document.getElementById("boot").textContent);
  var content = boot.content, CSRF = boot.csrf, FONTS = boot.fonts;
  var dirty = false;
  var _fetch = window.fetch.bind(window);
  window.fetch = function (url, opts) {
    return _fetch(url, Object.assign({ credentials: "same-origin" }, opts || {})).then(function (r) {
      var ct = r.headers.get("content-type") || "";
      if (String(url).indexOf("?action=") === 0 && ct.indexOf("application/json") < 0 && String(url).indexOf("action=export") < 0) {
        setStatus("Your session expired. Reload this page and sign in again.", "err");
        throw new Error("session");
      }
      return r;
    });
  };
  var $ = function (s, r) { return (r || document).querySelector(s); };
  var el = function (tag, attrs, kids) {
    var n = document.createElement(tag);
    for (var k in (attrs || {})) {
      if (k === "text") n.textContent = attrs[k];
      else if (k === "html") n.innerHTML = attrs[k];
      else if (k.slice(0, 2) === "on") n.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] !== undefined && attrs[k] !== null && attrs[k] !== false) n.setAttribute(k, attrs[k]);
    }
    (kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === "string" ? document.createTextNode(c) : c); });
    return n;
  };

  // ------------------------------------------------------------ labels & help
  var SECTION_NAMES = {
    site: "Site settings", theme: "Colours & fonts", nav: "Top bar", hero: "Opening (hero)",
    evidence: "The evidence (numbers)", long_view: "The long view (history)", map: "The one map", days: "Programme (two days)", speakers: "Speakers", experience: "The experience (art, film, food)", city: "Amsterdam photo band",
    different: "Why it's different", room: "The room", movement: "The movement (XX+)", apply: "Take part (forms)", weak_signal: "Weak Signal (home page)", signal_page: "Weak Signal pages", footer: "Footer"
  };
  var LABELS = {
    title_line_1: "Title, first line", title_line_2: "Title, second line (italic)", lede: "Opening paragraph",
    button_invite: "Invitation button", button_1: "First button", button_1_href: "First button links to", button_2: "Second button", button_2_href: "Second button links to", button_partner: "Partner button", button_witness: "Speaker button",
    image_alt: "Image description (for screen readers)", image_caption: "Image caption", share_image: "Share image",
    contact_email: "Public contact email", notify_email: "Send form notifications to",
    colors_light: "Paper theme (light)", colors_dark: "Ink theme (dark)", mode: "Colour mode",
    paper: "Page background", paper_raised: "Paper raised (panels)", ink: "Ink (text, mark)", ink_muted: "Ink muted (secondary text)",
    rule: "Rule (hairlines)", stone: "Stone (inactive geometry)", vermilion: "Vermilion (the one accent)", vermilion_text: "Vermilion for small text",
    canal: "Canal (research, buttons on hover)", signal: "Signal (Weak Signal only)", edition: "Edition accent (Forum I cobalt)", on_edition: "Text on edition colour",
    sans: "Grotesk (headlines, labels, interface)", serif: "Serif (ledes, quotes, long text)",
    map_links: "Map links", map_highlight: "Highlight on the map", column_usual: "Left column heading", column_forum: "Right column heading",
    text_1: "Text, first paragraph", text_2: "Text, second paragraph", leave_label: "'You leave with' label",
    line_1: "Line 1", line_2: "Line 2", line_3: "Line 3", url: "Source link", href: "Link to", people: "Speakers", slot: "Slot (e.g. Keynote · Power)", placeholder: "Text for unnamed slots", kinds: "Kinds of voices", moments: "Moments in history", when: "When", items: "Cards", cta_text: "Call-to-action text", cta_button: "Call-to-action button"
  };
  var HELP = {
    "site.title": "Shown in the browser tab and when the link is shared.",
    "site.description": "Shown in search results and link previews. Keep under 160 characters.",
    "site.share_image": "Shown when the link is shared on LinkedIn, WhatsApp, X. Use 1200 × 630 px.",
    "site.contact_email": "Shown in the footer. Leave empty to hide.",
    "site.notify_email": "Each form submission is emailed here. All submissions are also kept under Submissions.",
    "theme.mode": "Auto follows the visitor's device. Light or Dark forces one look.",
    "theme.fonts": "The design system uses Schibsted Grotesk and Newsreader (self-hosted). Other choices load from Google Fonts; never add a third family.",
    "theme.colors_light": "Two colours: cobalt #1F3BFF (structure, links, numbers — the 'Vermilion', 'Canal' and 'Edition' slots) and pink #FF4FA3 (action, highlights — the 'Signal' slot) on cream #FFF8EC.",
    "theme.colors_dark": "The Ink theme inverts paper and ink; vermilion stays the smallest field.",
    "hero.image": "Optional. Portrait photo beside the title, about 1200 × 1500 px. Leave empty for the type-only opening.",
    "evidence.shifts.image": "Optional. Landscape photo for this shift, about 1600 × 900 px.",
    "evidence.shifts.map_links": "Which separations this shift connects to on the map, by number: 1 = first separation in 'The one map'. Example: 2, 4",
    "evidence.shifts.map_highlight": "Draws this shift's lines in the signal colour (used for Intelligence).",
    "days.movements.items": "Use **double asterisks** for bold.",
    "days.leave": "Use **double asterisks** for bold.",
    "city.show": "Show a full-width Amsterdam photograph between the map and the two days.",
    "city.image": "Wide photo, about 2400 × 900 px.",
    "room.segments": "The bar is drawn in proportion to each number.",
    "speakers.people": "One card per speaker. Leave the name empty to show the slot as 'To be announced'. Add, remove or reorder freely.",
    "speakers.people.image": "Square portrait, at least 800 × 800 px. Real photograph, slightly desaturated; no stage shots with logos.",
    "speakers.people.role": "Title and organisation, one line.",
    "speakers.kinds": "The kinds of voices on stage, shown as a row of tags under the intro.",
    "long_view.moments": "A short timeline. The last item is drawn as the present moment. Leave the title empty to hide the whole section.",
    "experience.items": "Art, film, food, performance and so on. Add, remove or reorder.",
    "experience.items.image": "Optional. Landscape photo, about 1600 × 1000 px.",
    "experience.note": "One line shown in large italic under the cards. Leave empty to hide.",
    "movement.title": "The XX+ band, in vermilion, between The room and Take part. Leave the title empty to hide the whole section.",
    "movement.button_1_href": "A section on this page (#apply, #weak-signal) or a full web address. #apply opens the invitation form."
  };
  var LONG = ["lede", "intro", "text", "text_1", "text_2", "note", "quote", "description", "outro", "kicker", "question", "privacy", "thanks"];
  var human = function (k) { return LABELS[k] || (k.charAt(0).toUpperCase() + k.slice(1)).replace(/_/g, " "); };
  var helpFor = function (path) { return HELP[path.replace(/\.\d+/g, "")] || ""; };
  var markDirty = function () { dirty = true; $("#save").disabled = false; setStatus("Unsaved changes"); };
  var setStatus = function (t, cls) { var s = $("#status"); s.textContent = t; s.className = "status " + (cls || ""); };

  // ------------------------------------------------------------ fields
  function field(obj, key, path) {
    var val = obj[key];
    var wrap = el("div", { "class": "field" });
    var id = "f-" + path.replace(/[^a-z0-9]/gi, "-");
    var label = el("label", { "for": id, text: human(key) });
    var help = helpFor(path);
    var set = function (v) { obj[key] = v; markDirty(); };

    if (/^theme\.colors_/.test(path)) {
      var txt = el("input", { id: id, value: val, maxlength: "9", "class": "hex" });
      var pick = el("input", { type: "color", value: /^#[0-9a-f]{6}$/i.test(val) ? val : "#000000", "aria-label": human(key) + " picker" });
      pick.addEventListener("input", function () { txt.value = pick.value.toUpperCase(); set(txt.value); });
      txt.addEventListener("input", function () { if (/^#[0-9a-f]{6}$/i.test(txt.value)) pick.value = txt.value; set(txt.value); });
      wrap.classList.add("color");
      wrap.append(label, el("div", { "class": "row" }, [pick, txt]));
      return wrap;
    }
    if (/^theme\.fonts\./.test(path)) {
      var choices = FONTS[key] || [];
      var sel = el("select", { id: id });
      choices.concat(choices.indexOf(val) < 0 ? [val] : []).forEach(function (f) { sel.appendChild(el("option", { value: f, text: f })); });
      sel.appendChild(el("option", { value: "__other", text: "Other Google Font…" }));
      sel.value = val;
      var other = el("input", { placeholder: "Exact Google Fonts family name", hidden: true });
      var sample = el("p", { "class": "font-sample", text: "The Great Reconfiguration · 1.60°C · 900m" });
      if (val === "Schibsted Grotesk" || val === "Newsreader") { sample.style.fontFamily = '"' + val + '", serif'; }
      var loadFont = function (f) {
        if (!/^[A-Za-z0-9 ]{2,60}$/.test(f)) return;
        if (f === "Schibsted Grotesk" || f === "Newsreader") { sample.style.fontFamily = '"' + f + '", serif'; return; }
        var href = "https://fonts.googleapis.com/css2?family=" + f.replace(/ /g, "+") + ":wght@400;500;600&display=swap";
        if (!document.querySelector('link[href="' + href + '"]')) document.head.appendChild(el("link", { rel: "stylesheet", href: href }));
        sample.style.fontFamily = '"' + f + '", serif';
      };
      sel.addEventListener("change", function () {
        if (sel.value === "__other") { other.hidden = false; other.focus(); return; }
        other.hidden = true; set(sel.value); loadFont(sel.value);
      });
      other.addEventListener("input", function () { set(other.value.trim()); loadFont(other.value.trim()); });
      loadFont(val);
      wrap.append(label, sel, other, sample);
      if (help) wrap.appendChild(el("p", { "class": "help", text: help }));
      return wrap;
    }
    if (path === "theme.mode") {
      var m = el("select", { id: id });
      [["auto", "Auto (follow the visitor's device)"], ["light", "Always light"], ["dark", "Always dark"]].forEach(function (o) { m.appendChild(el("option", { value: o[0], text: o[1] })); });
      m.value = val; m.addEventListener("change", function () { set(m.value); });
      wrap.append(label, m);
      if (help) wrap.appendChild(el("p", { "class": "help", text: help }));
      return wrap;
    }
    if (key === "image" || key === "share_image") return imageField(obj, key, path, label, help);
    if (typeof val === "boolean") {
      var cb = el("input", { type: "checkbox", id: id });
      cb.checked = val; cb.addEventListener("change", function () { set(cb.checked); });
      wrap.classList.add("check");
      wrap.append(el("div", { "class": "row" }, [cb, label]));
      if (help) wrap.appendChild(el("p", { "class": "help", text: help }));
      return wrap;
    }
    var isLong = LONG.indexOf(key) >= 0 || (typeof val === "string" && val.length > 90);
    var input = isLong ? el("textarea", { id: id, rows: Math.min(8, Math.max(3, Math.ceil(String(val).length / 70))) }) : el("input", { id: id, type: (key === "url" || key === "href") ? "text" : (/email/.test(key) ? "email" : "text") });
    input.value = val == null ? "" : val;
    input.addEventListener("input", function () { set(input.value); });
    wrap.append(label, input);
    if (help) wrap.appendChild(el("p", { "class": "help", text: help }));
    return wrap;
  }

  function imageField(obj, key, path, label, help) {
    var wrap = el("div", { "class": "field image" });
    var preview = el("div", { "class": "img-preview" });
    var render = function () {
      preview.innerHTML = "";
      if (obj[key]) preview.appendChild(el("img", { src: "../" + obj[key], alt: "" }));
      else preview.appendChild(el("span", { text: "No image. The design works without one." }));
      remove.hidden = !obj[key];
    };
    var file = el("input", { type: "file", accept: "image/jpeg,image/png,image/webp,image/gif", hidden: true });
    var pickBtn = el("button", { type: "button", "class": "ghost", text: "Upload image", onclick: function () { file.click(); } });
    var remove = el("button", { type: "button", "class": "ghost danger", text: "Remove", onclick: function () { obj[key] = ""; markDirty(); render(); } });
    file.addEventListener("change", function () {
      if (!file.files[0]) return;
      var fd = new FormData(); fd.append("file", file.files[0]); fd.append("csrf", CSRF);
      pickBtn.disabled = true; pickBtn.textContent = "Uploading…";
      fetch("?action=upload", { method: "POST", body: fd, headers: { "X-CSRF": CSRF } })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok) { obj[key] = j.path; markDirty(); render(); setStatus("Image uploaded (" + j.width + " × " + j.height + "). Save to publish.", ""); }
          else setStatus(j.error || "Upload failed.", "err");
        })
        .catch(function () { setStatus("Upload failed. Check your connection.", "err"); })
        .then(function () { pickBtn.disabled = false; pickBtn.textContent = "Upload image"; file.value = ""; });
    });
    render();
    wrap.append(label, preview, el("div", { "class": "row" }, [pickBtn, remove, file]));
    if (help) wrap.appendChild(el("p", { "class": "help", text: help }));
    return wrap;
  }

  function blankLike(v) {
    if (Array.isArray(v)) return v.length && typeof v[0] !== "object" ? [""] : v.map(blankLike).slice(0, 1);
    if (v && typeof v === "object") { var o = {}; for (var k in v) o[k] = blankLike(v[k]); return o; }
    if (typeof v === "boolean") return false;
    return "";
  }

  function arrayField(arr, key, path) {
    var box = el("div", { "class": "array" });
    box.appendChild(el("h4", { text: human(key) }));
    var help = helpFor(path);
    if (help) box.appendChild(el("p", { "class": "help", text: help }));
    var list = el("div", { "class": "items" });
    var redraw = function () {
      list.innerHTML = "";
      arr.forEach(function (item, i) {
        var tools = el("div", { "class": "item-tools" }, [
          el("button", { type: "button", "class": "mini", title: "Move up", "aria-label": "Move up", text: "↑", disabled: i === 0 ? "disabled" : null, onclick: function () { arr.splice(i - 1, 0, arr.splice(i, 1)[0]); markDirty(); redraw(); } }),
          el("button", { type: "button", "class": "mini", title: "Move down", "aria-label": "Move down", text: "↓", disabled: i === arr.length - 1 ? "disabled" : null, onclick: function () { arr.splice(i + 1, 0, arr.splice(i, 1)[0]); markDirty(); redraw(); } }),
          el("button", { type: "button", "class": "mini danger", title: "Remove", "aria-label": "Remove", text: "✕", onclick: function () { arr.splice(i, 1); markDirty(); redraw(); } })
        ]);
        if (item && typeof item === "object") {
          var title = item.name || item.label || item.title || item.value || item.when || item.number || ("Item " + (i + 1));
          var d = el("details", { "class": "item", open: arr.length <= 4 ? "open" : null });
          d.appendChild(el("summary", {}, [el("span", { text: String(title) }), tools]));
          d.appendChild(objectFields(item, path + "." + i));
          list.appendChild(d);
        } else {
          var holder = { v: item };
          var row = el("div", { "class": "item flat" });
          var inp = String(item).length > 60 ? el("textarea", { rows: 2, "aria-label": human(key) + " " + (i + 1) }) : el("input", { "aria-label": human(key) + " " + (i + 1) });
          inp.value = item; inp.addEventListener("input", function () { arr[i] = inp.value; markDirty(); });
          row.append(inp, tools); list.appendChild(row);
        }
      });
    };
    redraw();
    box.append(list, el("button", { type: "button", "class": "ghost add", text: "Add " + human(key).replace(/s$/, "").toLowerCase(), onclick: function () {
      arr.push(arr.length ? blankLike(arr[arr.length - 1]) : ""); markDirty(); redraw();
    } }));
    return box;
  }

  function objectFields(obj, path) {
    var g = el("div", { "class": "group" });
    Object.keys(obj).forEach(function (k) {
      var p = path ? path + "." + k : k, v = obj[k];
      if (Array.isArray(v)) g.appendChild(arrayField(v, k, p));
      else if (v && typeof v === "object") {
        var sub = el("fieldset", { "class": "sub" });
        sub.appendChild(el("legend", { text: human(k) }));
        var h = helpFor(p); if (h) sub.appendChild(el("p", { "class": "help", text: h }));
        sub.appendChild(objectFields(v, p));
        g.appendChild(sub);
      } else g.appendChild(field(obj, k, p));
    });
    return g;
  }

  // ------------------------------------------------------------ views
  var main = $("#main"), side = $("#sidenav"), views = $("#views");
  var current = "content";

  function renderContent(section) {
    main.innerHTML = ""; side.innerHTML = ""; side.hidden = false;
    var order = Object.keys(content).filter(function (k) { return k !== "site" && k !== "theme"; });
    order.forEach(function (k) {
      side.appendChild(el("a", { href: "#", "class": k === section ? "on" : "", text: SECTION_NAMES[k] || human(k), onclick: function (e) { e.preventDefault(); renderContent(k); } }));
    });
    section = section || order[0];
    main.append(el("h2", { text: SECTION_NAMES[section] || human(section) }), objectFields(content[section], section));
  }
  function renderSingle(key, intro) {
    main.innerHTML = ""; side.innerHTML = ""; side.hidden = true;
    main.append(el("h2", { text: SECTION_NAMES[key] }));
    if (intro) main.appendChild(el("p", { "class": "lead", text: intro }));
    main.appendChild(objectFields(content[key], key));
  }

  function renderSubmissions(kind) {
    main.innerHTML = ""; side.innerHTML = ""; side.hidden = true;
    var kinds = [["", "All"], ["invitation", "Invitations"], ["partner", "Partners"], ["speaker", "Speakers"], ["witness", "Witnesses (old)"], ["weak-signal", "Weak Signal"]];
    var filter = el("select", { "aria-label": "Show" });
    kinds.forEach(function (k) { filter.appendChild(el("option", { value: k[0], text: k[1] })); });
    filter.value = kind || "";
    var exportLink = el("a", { "class": "ghost", href: "?action=export&t=" + encodeURIComponent(CSRF) + "&kind=" + encodeURIComponent(kind || ""), text: "Download CSV" });
    filter.addEventListener("change", function () { renderSubmissions(filter.value); });
    main.append(el("h2", { text: "Submissions" }), el("div", { "class": "row toolbar" }, [filter, exportLink]));
    var holder = el("div", { "class": "subs", text: "Loading…" }); main.appendChild(holder);
    fetch("?action=submissions", { headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }).then(function (j) {
      holder.innerHTML = "";
      var rows = (j.rows || []).filter(function (r) { return !kind || r.kind === kind; });
      if (!rows.length) { holder.appendChild(el("p", { "class": "empty", text: "Nothing yet. Requests from the website's forms appear here." })); return; }
      rows.forEach(function (r) {
        var dl = el("dl");
        ["name", "email", "organisation", "role", "country", "domain", "seats", "tier", "speaker", "format", "witness", "shift", "link", "note"].forEach(function (f) {
          if (r[f]) { dl.appendChild(el("dt", { text: human(f) })); dl.appendChild(el("dd", { text: r[f] })); }
        });
        var card = el("article", { "class": "sub-card" }, [
          el("header", {}, [el("span", { "class": "pill " + r.kind, text: r.kind }), el("time", { text: new Date(r.received).toLocaleString() }),
            el("button", { type: "button", "class": "mini danger", text: "Delete", onclick: function () {
              if (!card.classList.contains("confirm")) { card.classList.add("confirm"); this.textContent = "Click again to delete"; return; }
              var fd = new FormData(); fd.append("id", r.id);
              fetch("?action=delete_submission", { method: "POST", body: fd, headers: { "X-CSRF": CSRF } }).then(function () { renderSubmissions(kind); });
            } })]),
          dl
        ]);
        holder.appendChild(card);
      });
    });
  }

  function renderAccount() {
    main.innerHTML = ""; side.innerHTML = ""; side.hidden = true;
    var msg = el("p", { "class": "status", role: "status" });
    var f = el("form", { "class": "group narrow" }, [
      el("div", { "class": "field" }, [el("label", { "for": "pw0", text: "Current password" }), el("input", { id: "pw0", type: "password", name: "current", required: "required", autocomplete: "current-password" })]),
      el("div", { "class": "field" }, [el("label", { "for": "pw1", text: "New password (10+ characters)" }), el("input", { id: "pw1", type: "password", name: "new", required: "required", minlength: "10", autocomplete: "new-password" })]),
      el("div", { "class": "field" }, [el("label", { "for": "pw2", text: "Repeat new password" }), el("input", { id: "pw2", type: "password", name: "new2", required: "required", minlength: "10", autocomplete: "new-password" })]),
      el("button", { type: "submit", "class": "primary", text: "Change password" }), msg
    ]);
    f.addEventListener("submit", function (e) {
      e.preventDefault();
      fetch("?action=password", { method: "POST", body: new FormData(f), headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }).then(function (j) {
        msg.textContent = j.ok ? "Password changed." : (j.error || "Not changed."); msg.className = "status " + (j.ok ? "ok" : "err"); if (j.ok) f.reset();
      });
    });
    var backups = el("div", { "class": "backups", text: "Loading…" });
    main.append(el("h2", { text: "Account & backups" }), f, el("h3", { text: "Content backups" }),
      el("p", { "class": "lead", text: "Every save keeps a copy of the previous version (the last 30). Restoring replaces the live content." }), backups);
    fetch("?action=backups", { headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }).then(function (j) {
      backups.innerHTML = "";
      if (!(j.backups || []).length) { backups.appendChild(el("p", { "class": "empty", text: "No backups yet. One is made each time you save." })); return; }
      j.backups.forEach(function (b) {
        var m = b.match(/content-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})/);
        var label = m ? m[3] + "/" + m[2] + "/" + m[1] + " " + m[4] + ":" + m[5] : b;
        backups.appendChild(el("div", { "class": "row backup" }, [el("span", { text: label }), el("button", { type: "button", "class": "ghost", text: "Restore", onclick: function () {
          var btn = this;
          if (btn.dataset.confirm !== "1") { btn.dataset.confirm = "1"; btn.textContent = "Click again to restore"; return; }
          var fd = new FormData(); fd.append("file", b);
          fetch("?action=restore", { method: "POST", body: fd, headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }).then(function (j2) {
            if (j2.ok) { dirty = false; location.reload(); } else setStatus(j2.error || "Restore failed.", "err");
          });
        } })]));
      });
    });
  }

  function renderUpdates() {
    main.innerHTML = ""; side.innerHTML = ""; side.hidden = true;
    var post = function (action, fd) { return fetch("?action=" + action, { method: "POST", body: fd || new FormData(), headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }); };
    var fmt = function (v) { return v && v.sha ? v.sha.slice(0, 7) + (v.date ? " · " + new Date(v.date).toLocaleString() : "") + (v.message ? " · " + v.message : "") : "none yet"; };
    var cur = el("p", { text: "Loading…" });
    var msg = el("p", { "class": "status", role: "status" });
    var btnCheck = el("button", { type: "button", "class": "ghost", text: "Check for updates" });
    var btnRun = el("button", { type: "button", "class": "primary", text: "Update website" });
    var btnBack = el("button", { type: "button", "class": "ghost", text: "Roll back last update" });
    var say = function (t, ok) { msg.textContent = t; msg.className = "status " + (ok ? "ok" : "err"); };

    var repo = el("input", { id: "u-repo", name: "repo", placeholder: "owner/name" });
    var branch = el("input", { id: "u-branch", name: "branch", value: "main" });
    var token = el("input", { id: "u-token", name: "token", type: "password", autocomplete: "off", placeholder: "Only for a private repository" });
    var tokenHelp = el("p", { "class": "help" });
    var smsg = el("p", { "class": "status", role: "status" });
    var sform = el("form", { "class": "group narrow" }, [
      el("div", { "class": "field" }, [el("label", { "for": "u-repo", text: "GitHub repository" }), repo]),
      el("div", { "class": "field" }, [el("label", { "for": "u-branch", text: "Branch" }), branch]),
      el("div", { "class": "field" }, [el("label", { "for": "u-token", text: "Access token (read-only)" }), token, tokenHelp]),
      el("button", { type: "submit", "class": "ghost", text: "Save settings" }), smsg
    ]);
    sform.addEventListener("submit", function (e) {
      e.preventDefault();
      post("update_settings", new FormData(sform)).then(function (j) {
        smsg.textContent = j.ok ? "Saved." : (j.error || "Not saved."); smsg.className = "status " + (j.ok ? "ok" : "err");
        if (j.ok) { token.value = ""; show(j.settings); }
      });
    });
    var show = function (st) { repo.value = st.repo || ""; branch.value = st.branch || "main"; tokenHelp.textContent = st.has_token ? "A token is saved. Leave empty to keep it." : "No token saved. Not needed for a public repository."; };

    btnCheck.addEventListener("click", function () {
      say("Checking…", true);
      post("update_check").then(function (j) {
        if (!j.ok) return say(j.error, false);
        say(j.available ? "An update is ready: " + fmt(j.latest) : "The website is up to date.", true);
      }).catch(function () { say("Could not check. If this repeats, reload the page and sign in again.", false); });
    });
    btnRun.addEventListener("click", function () {
      if (dirty) return say("Save or discard your unsaved changes first.", false);
      if (btnRun.dataset.confirm !== "1") { btnRun.dataset.confirm = "1"; btnRun.textContent = "Click again to update"; return; }
      btnRun.disabled = true; btnRun.textContent = "Updating…"; say("Downloading and installing. This takes a few seconds.", true);
      post("update_run").then(function (j) {
        btnRun.disabled = false; btnRun.dataset.confirm = ""; btnRun.textContent = "Update website";
        if (!j.ok) return say(j.error, false);
        var extra = (j.new_sections || []).length ? " New sections added: " + j.new_sections.join(", ") + "." : "";
        say("Updated to " + fmt(j.version) + "." + extra + " Your edits were kept. Reloading…", true);
        setTimeout(function () { location.reload(); }, 2500);
      }).catch(function () { btnRun.disabled = false; btnRun.textContent = "Update website"; say("The update did not finish. Reload this page and check the site; use Roll back if needed.", false); });
    });
    btnBack.addEventListener("click", function () {
      if (btnBack.dataset.confirm !== "1") { btnBack.dataset.confirm = "1"; btnBack.textContent = "Click again to roll back"; return; }
      post("update_rollback").then(function (j) {
        btnBack.dataset.confirm = ""; btnBack.textContent = "Roll back last update";
        if (!j.ok) return say(j.error, false);
        say("Rolled back. Reloading…", true); setTimeout(function () { location.reload(); }, 1500);
      });
    });

    main.append(el("h2", { text: "Updates" }),
      el("p", { "class": "lead", text: "Design and layout changes arrive from GitHub. Updating replaces only the site's code: your text, images, colours, password and submissions are kept, and new sections are added without overwriting what you edited." }),
      el("h3", { text: "Installed version" }), cur,
      el("div", { "class": "row" }, [btnRun, btnCheck, btnBack]), msg,
      el("h3", { text: "Settings" }), sform);
    fetch("?action=update_status", { headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }).then(function (j) {
      cur.textContent = fmt(j.current); show(j.settings || {});
    });
  }

  // ------------------------------------------------------------ Weak Signal
  var WS = null;
  var wsPost = function (action, fd) { return fetch("?action=" + action, { method: "POST", body: fd, headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }); };
  var num3 = function (n) { return ("00" + n).slice(-3); };
  function wsLoad(then) {
    fetch("?action=ws_list", { headers: { "X-CSRF": CSRF } }).then(function (r) { return r.json(); }).then(function (j) { WS = j; then(); });
  }
  function renderSignal(sub, arg) {
    main.innerHTML = ""; side.innerHTML = ""; side.hidden = false;
    [["signals", "Signals"], ["subscribers", "Subscribers"], ["settings", "Email settings"]].forEach(function (v) {
      side.appendChild(el("a", { href: "#", "class": v[0] === (sub || "signals") ? "on" : "", text: v[1], onclick: function (e) { e.preventDefault(); renderSignal(v[0]); } }));
    });
    side.appendChild(el("a", { href: "../signal/", target: "_blank", rel: "noopener", text: "Open Weak Signal ↗" }));
    main.appendChild(el("p", { text: "Loading…" }));
    wsLoad(function () {
      main.innerHTML = "";
      if (sub === "subscribers") wsSubscribers();
      else if (sub === "settings") wsSettings();
      else if (sub === "edit") wsEditor(arg);
      else wsSignalList();
    });
  }
  function wsSignalList() {
    main.append(el("h2", { text: "Weak Signal · Signals" }),
      el("p", { "class": "lead", text: "Each signal has a number and a title. Publishing puts it in the subscriber archive at amsforum.com/signal; sending emails the title and opening lines to every active subscriber, with a link to read the rest after signing in." }),
      el("div", { "class": "row toolbar" }, [el("button", { type: "button", "class": "primary", text: "+ New signal", onclick: function () { renderSignal("edit", null); } }),
        el("span", { "class": "help", text: WS.active + " active subscriber" + (WS.active === 1 ? "" : "s") })]));
    if (!WS.signals.length) { main.appendChild(el("p", { "class": "empty", text: "No signals yet. Start with No. 001." })); return; }
    var list = el("div", { "class": "subs" });
    WS.signals.forEach(function (s) {
      list.appendChild(el("article", { "class": "sub-card ws-card" }, [
        el("header", {}, [
          el("span", { "class": "pill " + (s.published ? "speaker" : ""), text: s.published ? "Published" : "Draft" }),
          el("time", { text: "No. " + num3(s.number) + " · " + s.date + (s.sent_at ? " · emailed to " + s.sent_count : " · not emailed") }),
          el("button", { type: "button", "class": "ghost", text: "Edit", onclick: function () { renderSignal("edit", s.id); } })
        ]),
        el("p", { style: "margin:0;font-weight:600;font-size:1.1rem", text: s.title }),
        el("p", { "class": "help", text: (WS.statuses[s.status] || s.status) + (s.shifts.length ? " · " + s.shifts.join(" · ") : "") })
      ]));
    });
    main.appendChild(list);
  }
  function wsEditor(id) {
    var s = null;
    WS.signals.forEach(function (x) { if (x.id === id) s = x; });
    var maxN = 0; WS.signals.forEach(function (x) { maxN = Math.max(maxN, x.number); });
    s = s ? JSON.parse(JSON.stringify(s)) : { number: maxN + 1, title: "", date: new Date().toISOString().slice(0, 10), shifts: [], status: "open", status_note: "", published: false, observed: "", doesnt_fit: "", if_real: "", implication: "" };
    var msg = el("p", { "class": "status", role: "status" });
    var say = function (t, ok) { msg.textContent = t; msg.className = "status " + (ok ? "ok" : "err"); };
    var inp = function (k, label, type, help) {
      var i = el(type === "textarea" ? "textarea" : "input", { id: "ws-" + k, rows: type === "textarea" ? "7" : null, type: type === "textarea" ? null : (type || "text") });
      i.value = s[k] == null ? "" : s[k];
      i.addEventListener("input", function () { s[k] = type === "number" ? parseInt(i.value || "0", 10) : i.value; });
      return el("div", { "class": "field" }, [el("label", { "for": "ws-" + k, text: label }), i, help ? el("p", { "class": "help", text: help }) : null]);
    };
    var shiftBox = el("div", { "class": "row" });
    WS.shifts.forEach(function (name) {
      var cb = el("input", { type: "checkbox", id: "sh-" + name });
      cb.checked = s.shifts.indexOf(name) >= 0;
      cb.addEventListener("change", function () { s.shifts = s.shifts.filter(function (x) { return x !== name; }); if (cb.checked) s.shifts.push(name); });
      shiftBox.appendChild(el("label", { "for": "sh-" + name, "class": "row", style: "gap:6px;font-weight:400;margin-right:10px" }, [cb, name]));
    });
    var status = el("select", { id: "ws-status" });
    Object.keys(WS.statuses).forEach(function (k) { status.appendChild(el("option", { value: k, text: WS.statuses[k] })); });
    status.value = s.status; status.addEventListener("change", function () { s.status = status.value; });
    var pub = el("input", { type: "checkbox", id: "ws-pub" }); pub.checked = !!s.published;
    pub.addEventListener("change", function () { s.published = pub.checked; });

    var save = function () {
      return fetch("?action=ws_save_signal", { method: "POST", headers: { "Content-Type": "application/json", "X-CSRF": CSRF }, body: JSON.stringify({ signal: s }) })
        .then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) { say(j.error || "Not saved.", false); return null; }
          s = j.signal; say("Saved.", true); return s;
        });
    };
    var testTo = el("input", { type: "email", placeholder: "your@email.com", style: "max-width:260px" });
    var sendBtn = el("button", { type: "button", "class": "primary", text: "Email to " + WS.active + " subscriber" + (WS.active === 1 ? "" : "s") });
    var progress = el("p", { "class": "help" });
    sendBtn.addEventListener("click", function () {
      if (!s.published) return say("Tick 'Published' and save before sending.", false);
      if (sendBtn.dataset.confirm !== "1") { sendBtn.dataset.confirm = "1"; sendBtn.textContent = (s.sent_at ? "Already sent once. " : "") + "Click again to send now"; return; }
      sendBtn.disabled = true;
      save().then(function (saved) {
        if (!saved) { sendBtn.disabled = false; return; }
        var sentTotal = 0, failed = [];
        var step = function (offset) {
          var fd = new FormData(); fd.append("id", s.id); fd.append("offset", offset);
          wsPost("ws_send", fd).then(function (j) {
            if (!j.ok) { sendBtn.disabled = false; return say(j.error, false); }
            sentTotal += j.sent; failed = failed.concat(j.failed || []);
            progress.textContent = "Sent " + sentTotal + " of " + j.total + "…";
            if (!j.done) return step(j.next);
            sendBtn.disabled = false; sendBtn.dataset.confirm = ""; sendBtn.textContent = "Email to subscribers";
            say("Done: sent to " + sentTotal + " of " + j.total + "." + (failed.length ? " Failed: " + failed.join(", ") : ""), !failed.length);
          }).catch(function () { sendBtn.disabled = false; say("Sending stopped (connection). Some subscribers may already have it; check with your host before sending again.", false); });
        };
        step(0);
      });
    });

    main.append(el("h2", { text: id ? "Edit No. " + num3(s.number) : "New signal" }),
      el("div", { "class": "group" }, [
        el("div", { "class": "row", style: "align-items:flex-start;gap:16px" }, [inp("number", "Number", "number"), inp("date", "Date", "date")]),
        inp("title", "Title", "text", "One line. It is the email subject and the archive headline."),
        el("div", { "class": "field" }, [el("label", { text: "Shifts" }), shiftBox]),
        el("p", { "class": "help", text: "The four parts never change. Separate paragraphs with an empty line; **double asterisks** for bold. The opening lines of part 1 appear in the email and to visitors who are not signed in." }),
        inp("observed", "01 · " + WS.parts.observed, "textarea"),
        inp("doesnt_fit", "02 · " + WS.parts.doesnt_fit, "textarea"),
        inp("if_real", "03 · " + WS.parts.if_real, "textarea"),
        inp("implication", "04 · " + WS.parts.implication, "textarea"),
        el("div", { "class": "row", style: "gap:16px;align-items:flex-end" }, [el("div", { "class": "field" }, [el("label", { "for": "ws-status", text: "Ledger status" }), status])]),
        inp("status_note", "Ledger note", "textarea", "What happened since, and the next review date. Shown under the signal."),
        el("label", { "for": "ws-pub", "class": "row", style: "gap:8px" }, [pub, "Published (visible to subscribers)"]),
        el("div", { "class": "row" }, [
          el("button", { type: "button", "class": "primary", text: "Save signal", onclick: function () { save().then(function (x) { if (x && !id) renderSignal("edit", x.id); }); } }),
          el("a", { "class": "ghost", href: "../signal/?n=" + s.number, target: "_blank", rel: "noopener", text: "Preview ↗" }),
          el("button", { type: "button", "class": "ghost", text: "Back to signals", onclick: function () { renderSignal("signals"); } })
        ]), msg,
        el("h3", { text: "Send" }),
        el("div", { "class": "row" }, [testTo, el("button", { type: "button", "class": "ghost", text: "Send a test", onclick: function () {
          save().then(function (x) { if (!x) return; var fd = new FormData(); fd.append("id", x.id); fd.append("email", testTo.value);
            wsPost("ws_test", fd).then(function (j) { say(j.ok ? "Test sent to " + testTo.value + "." : j.error, j.ok); }); });
        } })]),
        el("div", { "class": "row" }, [sendBtn]), progress,
        id ? el("button", { type: "button", "class": "mini danger", style: "justify-self:start;margin-top:24px", text: "Delete this signal", onclick: function () {
          if (this.dataset.confirm !== "1") { this.dataset.confirm = "1"; this.textContent = "Click again to delete permanently"; return; }
          var fd = new FormData(); fd.append("id", s.id); wsPost("ws_delete_signal", fd).then(function () { renderSignal("signals"); });
        } }) : null
      ]));
  }
  function wsSubscribers() {
    var msg = el("p", { "class": "status", role: "status" });
    var say = function (t, ok) { msg.textContent = t; msg.className = "status " + (ok ? "ok" : "err"); };
    var planSel = function (v) { var p = el("select"); Object.keys(WS.plans).forEach(function (k) { p.appendChild(el("option", { value: k, text: WS.plans[k] })); }); p.value = v || "founding"; return p; };
    var email = el("input", { type: "email", placeholder: "email", required: "required" }), name = el("input", { placeholder: "name (optional)" });
    var plan = planSel("founding"), exp = el("input", { type: "date", title: "Access until (empty = no end date)" });
    var add = el("form", { "class": "row" }, [email, name, plan, exp, el("button", { type: "submit", "class": "primary", text: "Add subscriber" })]);
    add.addEventListener("submit", function (e) {
      e.preventDefault();
      var fd = new FormData(); fd.append("email", email.value); fd.append("name", name.value); fd.append("plan", plan.value); fd.append("expires", exp.value); fd.append("status", "active");
      wsPost("ws_save_subscriber", fd).then(function (j) { if (!j.ok) return say(j.error, false); renderSignal("subscribers"); });
    });
    var lines = el("textarea", { rows: "4", placeholder: "One per line: email, name" });
    var leads = el("input", { type: "checkbox", id: "ws-leads" });
    var iplan = planSel("founding"), iexp = el("input", { type: "date" });
    var imp = el("button", { type: "button", "class": "ghost", text: "Import", onclick: function () {
      var fd = new FormData(); fd.append("lines", lines.value); fd.append("plan", iplan.value); fd.append("expires", iexp.value); if (leads.checked) fd.append("leads", "1");
      wsPost("ws_import", fd).then(function (j) { say("Added " + j.added + ", skipped " + j.skipped + " (already there or not an email).", true); setTimeout(function () { renderSignal("subscribers"); }, 1200); });
    } });
    var table = el("div", { "class": "subs" });
    WS.subscribers.slice().sort(function (a, b) { return a.email < b.email ? -1 : 1; }).forEach(function (r) {
      var st = el("select"); [["active", "Active"], ["paused", "Paused"]].forEach(function (o) { st.appendChild(el("option", { value: o[0], text: o[1] })); }); st.value = r.status;
      var pl = planSel(r.plan), ex = el("input", { type: "date", value: r.expires || "" });
      var saveRow = function () { var fd = new FormData(); fd.append("email", r.email); fd.append("plan", pl.value); fd.append("status", st.value); fd.append("expires", ex.value);
        wsPost("ws_save_subscriber", fd).then(function (j) { say(j.ok ? "Saved " + r.email + "." : j.error, j.ok); }); };
      [st, pl, ex].forEach(function (x) { x.addEventListener("change", saveRow); });
      table.appendChild(el("div", { "class": "row backup", style: "flex-wrap:wrap" }, [
        el("span", { style: "flex:1 1 220px;min-width:0;overflow-wrap:anywhere", text: r.email + (r.name ? " · " + r.name : "") + (r.last_login ? "" : " · never signed in") }), pl, st, ex,
        el("button", { type: "button", "class": "mini danger", text: "Remove", onclick: function () {
          if (this.dataset.confirm !== "1") { this.dataset.confirm = "1"; this.textContent = "Click again"; return; }
          var fd = new FormData(); fd.append("email", r.email); wsPost("ws_delete_subscriber", fd).then(function () { renderSignal("subscribers"); });
        } })
      ]));
    });
    main.append(el("h2", { text: "Weak Signal · Subscribers" }),
      el("p", { "class": "lead", text: WS.active + " active of " + WS.subscribers.length + ". Only active subscribers (and not past their end date) can sign in and receive emails. Add people here once they have paid or been invited. Changes save as you make them." }),
      add, msg, el("h3", { text: "Everyone" }), WS.subscribers.length ? table : el("p", { "class": "empty", text: "No subscribers yet." }),
      el("h3", { text: "Import" }),
      el("div", { "class": "group narrow", style: "max-width:560px" }, [lines,
        el("label", { "for": "ws-leads", "class": "row", style: "gap:8px;font-weight:400" }, [leads, "Also add everyone on the founding list from the website form (" + WS.leads + ")"]),
        el("div", { "class": "row" }, [iplan, iexp, imp])]));
  }
  function wsSettings() {
    var msg = el("p", { "class": "status", role: "status" });
    var from = el("input", { type: "email", value: WS.settings.custom_from || "", placeholder: WS.settings.from });
    var reply = el("input", { type: "email", value: WS.settings.reply_to || "", placeholder: "forum@amsforum.com" });
    var f = el("form", { "class": "group narrow" }, [
      el("div", { "class": "field" }, [el("label", { text: "Send from" }), from, el("p", { "class": "help", text: "Use an address at your domain (for example signal@amsforum.com), created in cPanel → Email Accounts, so emails are not marked as spam. Empty uses " + WS.settings.from + "." })]),
      el("div", { "class": "field" }, [el("label", { text: "Replies go to" }), reply]),
      el("button", { type: "submit", "class": "primary", text: "Save" }), msg]);
    f.addEventListener("submit", function (e) { e.preventDefault(); var fd = new FormData(); fd.append("from", from.value); fd.append("reply_to", reply.value);
      wsPost("ws_settings", fd).then(function (j) { msg.textContent = j.ok ? "Saved." : j.error; msg.className = "status " + (j.ok ? "ok" : "err"); }); });
    main.append(el("h2", { text: "Weak Signal · Email settings" }),
      el("p", { "class": "lead", text: "Signals and sign-in links are sent by your hosting's email. Shared hosting limits how many emails go out per hour (often a few hundred); beyond a few hundred subscribers, move sending to an email service." }), f);
  }

  var VIEWS = [

    ["content", "Page content", function () { renderContent(); }],
    ["theme", "Colours & fonts", function () { renderSingle("theme", "Colours apply across the whole site. Change one, save, and reload the site to see it."); }],
    ["site", "Site settings", function () { renderSingle("site", "Page title, link preview, emails."); }],
    ["signal", "Weak Signal", function () { renderSignal("signals"); }],
    ["subs", "Submissions", function () { renderSubmissions(""); }],
    ["updates", "Updates", renderUpdates],
    ["account", "Account", renderAccount]
  ];
  VIEWS.forEach(function (v) {
    views.appendChild(el("button", { type: "button", "data-view": v[0], "class": v[0] === current ? "on" : "", text: v[1], onclick: function () {
      current = v[0]; views.querySelectorAll("button").forEach(function (b) { b.classList.toggle("on", b.dataset.view === current); }); v[2]();
    } }));
  });
  renderContent();

  // ------------------------------------------------------------ save
  $("#save").addEventListener("click", function () {
    var btn = this; btn.disabled = true; setStatus("Saving…");
    fetch("?action=save", { method: "POST", headers: { "Content-Type": "application/json", "X-CSRF": CSRF }, body: JSON.stringify({ content: content }) })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok) { dirty = false; setStatus("Saved. The site is updated.", "ok"); }
        else { btn.disabled = false; setStatus(j.error || "Not saved.", "err"); }
      })
      .catch(function () { btn.disabled = false; setStatus("Not saved. Check your connection.", "err"); });
  });
  document.addEventListener("keydown", function (e) { if ((e.ctrlKey || e.metaKey) && e.key === "s") { e.preventDefault(); if (dirty) $("#save").click(); } });
  window.addEventListener("beforeunload", function (e) { if (dirty) { e.preventDefault(); e.returnValue = ""; } });
})();
