(function () {
  "use strict";

  // ---------- one map
  var dataEl = document.getElementById("map-data");
  if (dataEl) {
    var md = JSON.parse(dataEl.textContent || "{}");
    var shifts = md.shifts || [], seps = md.seps || [];
    var NS = "http://www.w3.org/2000/svg";
    var L = document.getElementById("left"), R = document.getElementById("right"), E = document.getElementById("edges");
    var lx = 210, rx = 590, top = 40, bottom = 400;
    var ly = function (i) { return shifts.length < 2 ? 220 : top + i * (bottom - top) / (shifts.length - 1); };
    var ry = function (j) { return seps.length < 2 ? 220 : 70 + j * (370 - 70) / (seps.length - 1); };
    var mk = function (t, a) { var e = document.createElementNS(NS, t); for (var k in a) e.setAttribute(k, a[k]); return e; };
    var edges = [];
    shifts.forEach(function (s, i) {
      (s.links || []).forEach(function (j) {
        if (j < 0 || j >= seps.length) return;
        var y1 = ly(i), y2 = ry(j), mx = (lx + rx) / 2;
        var p = mk("path", { d: "M" + lx + " " + y1 + " C " + mx + " " + y1 + ", " + mx + " " + y2 + ", " + rx + " " + y2, "class": "edge" + (s.hl ? " ai" : "") });
        p.setAttribute("data-s", i); E.appendChild(p); edges.push(p);
      });
    });
    shifts.forEach(function (s, i) {
      var g = mk("g", { "class": "s", tabindex: "0", role: "button", "aria-label": "Trace " + s.name });
      g.setAttribute("data-s", i);
      g.appendChild(mk("rect", { x: 0, y: ly(i) - 20, width: lx + 8, height: 40, fill: "transparent" }));
      var t = mk("text", { x: lx - 16, y: ly(i) + 5, "text-anchor": "end", "class": "lbl" }); t.textContent = s.name; g.appendChild(t);
      g.appendChild(mk("rect", { x: lx - 6, y: ly(i) - 6, width: 12, height: 12, "class": "node" }));
      L.appendChild(g);
    });
    seps.forEach(function (s, j) {
      R.appendChild(mk("rect", { x: rx - 5, y: ry(j) - 5, width: 10, height: 10, "class": "node-r" }));
      var t = mk("text", { x: rx + 16, y: ry(j) + 5, "class": "lbl-r" }); t.textContent = String(s).toUpperCase(); R.appendChild(t);
    });
    var box = document.getElementById("mapbox");
    var focus = function (i) { box.classList.add("focus"); edges.forEach(function (e) { e.classList.toggle("on", e.getAttribute("data-s") === String(i)); }); };
    var clear = function () { box.classList.remove("focus"); edges.forEach(function (e) { e.classList.remove("on"); }); };
    L.querySelectorAll("g.s").forEach(function (g) {
      var i = g.getAttribute("data-s");
      g.addEventListener("mouseenter", function () { focus(i); });
      g.addEventListener("focus", function () { focus(i); });
      g.addEventListener("click", function () { focus(i); });
      g.addEventListener("mouseleave", clear);
      g.addEventListener("blur", clear);
    });
  }

  // ---------- tabs
  var tabs = Array.prototype.slice.call(document.querySelectorAll(".tab"));
  var show = function (name) {
    tabs.forEach(function (t) {
      var on = t.getAttribute("data-tab") === name;
      t.setAttribute("aria-selected", on ? "true" : "false");
      var p = document.getElementById(t.getAttribute("aria-controls"));
      if (p) p.hidden = !on;
    });
  };
  tabs.forEach(function (t) { t.addEventListener("click", function () { show(t.getAttribute("data-tab")); }); });
  document.querySelectorAll("a[data-tab]").forEach(function (a) { a.addEventListener("click", function () { show(a.getAttribute("data-tab")); }); });

  // ---------- forms
  var EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  document.querySelectorAll("form[data-kind]").forEach(function (form) {
    form.addEventListener("submit", function (ev) {
      ev.preventDefault();
      var note = form.querySelector(".notice");
      note.classList.remove("warn");
      var missing = Array.prototype.filter.call(form.querySelectorAll("[required]"), function (el) {
        var v = el.value.trim();
        return !v || (el.type === "email" && !EMAIL.test(v));
      });
      if (missing.length) {
        note.hidden = false; note.classList.add("warn");
        note.textContent = "Please complete: " + missing.map(function (el) {
          var l = form.querySelector('label[for="' + el.id + '"]'); return l ? l.textContent.toLowerCase() : el.name;
        }).join(", ") + ".";
        missing[0].focus(); return;
      }
      var btn = form.querySelector('button[type="submit"]'); btn.disabled = true;
      var fd = new FormData(form); fd.append("kind", form.getAttribute("data-kind"));
      fetch("api/submit.php", { method: "POST", body: fd, headers: { "Accept": "application/json" } })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok && j.ok, j: j }; }); })
        .then(function (res) {
          note.hidden = false;
          if (res.ok) {
            note.textContent = form.getAttribute("data-thanks") || "Received. Thank you.";
            form.querySelectorAll("input:not([type=hidden]),textarea").forEach(function (el) { el.value = ""; });
          } else {
            note.classList.add("warn");
            note.textContent = (res.j && res.j.error) || "That didn't go through. Please try again in a moment.";
          }
        })
        .catch(function () {
          note.hidden = false; note.classList.add("warn");
          note.textContent = "That didn't go through. Please check your connection and try again.";
        })
        .then(function () { btn.disabled = false; });
    });
  });
})();
