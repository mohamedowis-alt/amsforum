(function () {
  "use strict";
  var boot = JSON.parse(document.getElementById("boot").textContent);
  var content = boot.content, CSRF = boot.csrf, FONTS = boot.fonts;
  var dirty = false;
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
    different: "Why it's different", room: "The room", apply: "Take part (forms)", weak_signal: "Weak Signal", footer: "Footer"
  };
  var LABELS = {
    title_line_1: "Title, first line", title_line_2: "Title, second line (italic)", lede: "Opening paragraph",
    button_invite: "Invitation button", button_partner: "Partner button", button_witness: "Speaker button",
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
    "theme.colors_light": "Page background: #FFFFFF for white, #F4F0E8 for the design system's warm paper. Values from the Amsterdam Forum design system. Vermilion: one moment per section, under 10% of the page. Edition: Forum I only, max 5%.",
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
    "experience.note": "One line shown in large italic under the cards. Leave empty to hide."
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
      }).catch(function () { say("Could not check. Try again.", false); });
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

  var VIEWS = [
    ["content", "Page content", function () { renderContent(); }],
    ["theme", "Colours & fonts", function () { renderSingle("theme", "Colours apply across the whole site. Change one, save, and reload the site to see it."); }],
    ["site", "Site settings", function () { renderSingle("site", "Page title, link preview, emails."); }],
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
