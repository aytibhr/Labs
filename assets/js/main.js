// assets/js/main.js
(function () {
  if (window.__NL_MAIN__) return;   // prevent double-binding
  window.__NL_MAIN__ = true;

  function ready(fn){
    if (document.readyState !== "loading") fn();
    else document.addEventListener("DOMContentLoaded", fn);
  }

  ready(function () {

    /* ===========================================
       MOBILE CARDS: header -> td[data-label]
       =========================================== */
    function applyMobileCards(scope) {
      const tables = scope
        ? [scope]
        : Array.from(document.querySelectorAll('.responsive-table'));

      tables.forEach(table => {
        if (!table) return;
        const thead = table.querySelector('thead');
        if (!thead) return;

        const headers = Array.from(thead.querySelectorAll('th')).map(th => th.textContent.trim());
        table.querySelectorAll('tbody tr').forEach(tr => {
          Array.from(tr.children).forEach((td, i) => {
            if (!td.hasAttribute('data-label') && headers[i]) {
              td.setAttribute('data-label', headers[i]);
            }
          });
          const actionTd = Array.from(tr.children).find(td =>
            td.classList.contains('actions') ||
            td.querySelector('.btn, .icon-btn, [data-action], [data-icon]')
          );
          if (actionTd) actionTd.classList.add('actions');
        });
      });
    }

    /* ===============================
       MOBILE NAV: TOGGLE + OVERLAY
       =============================== */
    const sidebar = document.querySelector('.sidebar');
    const toggles = Array.from(document.querySelectorAll('.mobile-menu-toggle'));

    if (sidebar && toggles.length) {
      // ensure a single overlay
      let overlay = document.querySelector('.mobile-menu-overlay');
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.className = 'mobile-menu-overlay';
        document.body.appendChild(overlay);
      }

      // helpers
      const isOpen = () => sidebar.classList.contains('sidebar-visible');
      const open = () => {
        sidebar.classList.add('sidebar-visible');
        overlay.classList.add('visible');
        overlay.style.pointerEvents = 'auto';
        document.body.style.overflow = 'hidden';
        toggles.forEach(b => b.setAttribute('aria-expanded','true'));
      };
      const close = () => {
        sidebar.classList.remove('sidebar-visible');
        overlay.classList.remove('visible');
        overlay.style.pointerEvents = 'none';
        document.body.style.overflow = '';
        toggles.forEach(b => b.setAttribute('aria-expanded','false'));
      };

      // init closed
      close();

      // Toggle on pointerup so outside pointerdown runs first
      toggles.forEach(btn => {
        btn.addEventListener('pointerup', (e) => {
          e.preventDefault();
          if (isOpen()) { close(); } else { open(); }
        });
        // keyboard support
        btn.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            if (isOpen()) { close(); } else { open(); }
          }
        });
      });

      // overlay click => close
      overlay.addEventListener('pointerdown', (e) => { e.preventDefault(); close(); });

      // outside click/tap => close (runs BEFORE toggle pointerup)
      document.addEventListener('pointerdown', (e) => {
        if (!isOpen()) return;
        const inSidebar = e.target.closest?.('.sidebar');
        const onToggle  = e.target.closest?.('.mobile-menu-toggle');
        const onHotspot = e.target.closest?.('#menu-hotspot'); // dashboard-only helper
        if (!inSidebar && !onToggle && !onHotspot) close();
      }, { capture: true });

      // Esc => close
      document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && isOpen()) close(); });

      // click any link inside sidebar => close (mobile)
      sidebar.addEventListener('click', (e) => {
        if (window.innerWidth <= 991 && e.target.closest('a')) close();
      });

      // desktop safety
      const onResize = () => { if (window.innerWidth >= 992) close(); };
      onResize();
      window.addEventListener('resize', onResize);

      // ==== Dashboard-only invisible hotspot (guards against overlays) ====
      const isDashboard = !!document.getElementById('revenueChart'); // detector
      let hotspot = document.getElementById('menu-hotspot');

      if (isDashboard) {
        if (!hotspot) {
          hotspot = document.createElement('button');
          hotspot.id = 'menu-hotspot';
          hotspot.type = 'button';
          hotspot.classList.add('mobile-menu-toggle'); // treat as toggle
          Object.assign(hotspot.style, {
            position: 'fixed',
            top: '0',
            left: '0',
            width: '64px',
            height: '64px',
            zIndex: '2147483647',
            background: 'transparent',
            border: '0',
            padding: '0',
            margin: '0',
            cursor: 'pointer',
            opacity: '0'
          });
          hotspot.setAttribute('aria-label', 'Open menu');
          document.body.appendChild(hotspot);
        }
        const syncHotspot = () => {
          hotspot.style.display = window.innerWidth <= 991 ? 'block' : 'none';
        };
        syncHotspot();
        window.addEventListener('resize', syncHotspot);

        hotspot.addEventListener('pointerup', (e) => {
          e.preventDefault();
          if (isOpen()) { close(); } else { open(); }
        });
      } else if (hotspot) {
        // not dashboard: remove if left over
        hotspot.remove();
      }
    }

    // run mobile card labeling once on load
    applyMobileCards();

    /* ===============================
       AUTO ACTIVE LINK (sidebar)
       =============================== */
    (function setActiveNav(){
      let path = (location.pathname.split("/").pop() || "").toLowerCase();
      // normalize common dashboard variants
      if (!path || path === "index.php") path = "dashboard.php";
      if (path === "dashboard") path = "dashboard.php";
    
      document.querySelectorAll(".sidebar-menu a").forEach(a => {
        const href = (a.getAttribute("href") || "").split("?")[0].toLowerCase();
        if (!href) return;
    
        // exact match first
        if (href === path) {
          a.classList.add("active"); a.setAttribute("aria-current","page");
          return;
        }
        // allow base match like 'dashboard' vs 'dashboard.php'
        const baseHref = href.replace(/\.php$/, "");
        const basePath = path.replace(/\.php$/, "");
        if (baseHref && basePath.startsWith(baseHref)) {
          a.classList.add("active"); a.setAttribute("aria-current","page");
        }
      });
    })();


    /* ===============================
       REUSABLE AJAX SEARCH (tables)
       =============================== */
    const searchInput = document.getElementById("ajax-search");
    const tableBody   = document.querySelector(".responsive-table tbody");
    if (searchInput && tableBody) {
      const doSearch = () => {
        const query = searchInput.value.trim();
        const page  = searchInput.dataset.page;
        const branchFilter = document.getElementById("branch_filter");
        const branchId = branchFilter ? branchFilter.value : "";

        if (query.length > 2 || query.length === 0) {
          fetch(`ajax_search.php?page=${encodeURIComponent(page)}&query=${encodeURIComponent(query)}&branch_id=${encodeURIComponent(branchId)}`)
            .then(r => r.text())
            .then(html => {
              tableBody.innerHTML = html || "<tr><td colspan='8' class='text-center'>No results found.</td></tr>";
              const tableEl = tableBody.closest('.responsive-table');
              if (tableEl) applyMobileCards(tableEl); // reapply after AJAX
            });
        }
      };
      searchInput.addEventListener("keyup", doSearch);
      const branchFilter = document.getElementById("branch_filter");
      branchFilter && branchFilter.addEventListener("change", doSearch);
    }

    /* ===============================
       DASHBOARD: DATE -> CHART DATA
       =============================== */
    const dateFilterForm = document.getElementById("date-filter-form");
    function fetchChartData(startDate="", endDate=""){
      const revenueCanvas = document.getElementById("revenueChart");
      const topTestsCanvas = document.getElementById("topTestsChart");
      if (!revenueCanvas || !topTestsCanvas || typeof Chart === "undefined") return;

      fetch(`ajax_get_chart_data.php?start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}`)
        .then(r => r.json())
        .then(data => {
          if (window.revenueChartInstance) window.revenueChartInstance.destroy();
          window.revenueChartInstance = new Chart(revenueCanvas.getContext("2d"), {
            type:"line",
            data:{ labels:data.revenue.labels, datasets:[{ label:"Revenue", data:data.revenue.data, borderColor:"rgba(79,209,197,1)", backgroundColor:"rgba(79,209,197,.2)", fill:true, tension:.4 }] },
            options:{ responsive:true, plugins:{ legend:{ display:false } } }
          });

          if (window.topTestsChartInstance) window.topTestsChartInstance.destroy();
          window.topTestsChartInstance = new Chart(topTestsCanvas.getContext("2d"), {
            type:"bar",
            data:{ labels:data.top_tests.labels, datasets:[{ label:"Times Billed", data:data.top_tests.data, backgroundColor:["#4FD1C5","#F6AD55","#4299E1","#9F7AEA","#ED64A6"] }] },
            options:{ responsive:true, indexAxis:"y", plugins:{ legend:{ display:false } } }
          });
        });
    }
    if (dateFilterForm) {
      dateFilterForm.addEventListener("submit", function(e){
        e.preventDefault();
        fetchChartData(
          document.getElementById("start_date").value,
          document.getElementById("end_date").value
        );
      });
    }
    if (document.getElementById("revenueChart")) fetchChartData(); // initial load
    
    // --- List date-range filter (Patients / Invoices) ---
    function initListRange(inputId, applyBtnId, pageSlug){
      const input     = document.getElementById(inputId);
      const applyBtn  = document.getElementById(applyBtnId);
      const tbody     = document.querySelector('.responsive-table tbody');
      if (!input || !applyBtn || !tbody) return;
    
      // Init Litepicker if available (same look as dashboard)
      if (typeof Litepicker !== 'undefined') {
        const today = new Date();
        const startDefault = new Date(); startDefault.setDate(today.getDate() - 30);
        new Litepicker({
          element: input,
          singleMode: false,
          numberOfMonths: 2,
          numberOfColumns: 2,
          format: 'YYYY-MM-DD',
          startDate: startDefault,
          endDate: today,
          maxDate: today,
          autoApply: true
        });
      }
    
      const searchInput  = document.getElementById('ajax-search');        // your existing search box
      const branchFilter = document.getElementById('branch_filter');      // optional branch filter
    
      function reloadList(){
        const [start='', end=''] = (input.value || '').split(' - ');
        const q  = searchInput ? searchInput.value.trim() : '';
        const b  = branchFilter ? branchFilter.value : '';
        const url = `ajax_search.php?page=${encodeURIComponent(pageSlug)}&query=${encodeURIComponent(q)}&branch_id=${encodeURIComponent(b)}&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;
    
        fetch(url)
          .then(r => r.text())
          .then(html => {
            tbody.innerHTML = html || "<tr><td colspan='8' class='text-center'>No results.</td></tr>";
            // re-apply mobile card labels, if your helper exists
            if (typeof applyMobileCards === 'function') {
              const tbl = tbody.closest('.responsive-table');
              tbl && applyMobileCards(tbl);
            }
          });
      }
    
      applyBtn.addEventListener('click', (e)=>{ e.preventDefault(); reloadList(); });
      if (searchInput)  searchInput.addEventListener('keyup', ()=>{ if (searchInput.value.length > 2 || searchInput.value.length === 0) reloadList(); });
      if (branchFilter) branchFilter.addEventListener('change', reloadList);
    }
    
    // Hook both pages (runs harmlessly if the IDs aren’t present)
    initListRange('patientsDateRange', 'applyPatientsFilter', 'patients');
    initListRange('invoicesDateRange', 'applyInvoicesFilter', 'invoices');


    /* ===============================
       AGE CALCULATION (DOB selects)
       =============================== */
    const dobDay = document.getElementById("dob_day"),
          dobMonth = document.getElementById("dob_month"),
          dobYear = document.getElementById("dob_year"),
          ageField = document.getElementById("age");

    function calculateAge(){
      if (dobDay?.value && dobMonth?.value && dobYear?.value) {
        const birth = new Date(dobYear.value, dobMonth.value - 1, dobDay.value);
        if (!isNaN(birth.getTime()) && birth.getMonth() === (dobMonth.value - 1)) {
          const today = new Date();
          let age = today.getFullYear() - birth.getFullYear();
          const m = today.getMonth() - birth.getMonth();
          if (m < 0 || (m === 0 && today.getDate() < birth.getDate())) age--;
          if (ageField) ageField.value = age >= 0 ? age : "";
        } else { if (ageField) ageField.value = ""; }
      }
    }
    dobDay?.addEventListener("change", calculateAge);
    dobMonth?.addEventListener("change", calculateAge);
    dobYear?.addEventListener("change", calculateAge);

    /* ===============================
       POS: Test selection summary
       =============================== */
    const testCards = document.querySelectorAll(".test-card"),
          selectedTestsList = document.getElementById("selected-tests-list"),
          totalAmountSpan = document.getElementById("total-amount"),
          hiddenTestsInput = document.getElementById("selected_tests_input");

    let selectedTests = [], totalAmount = 0;

    function updateSummary(){
      totalAmount = selectedTests.reduce((s,t)=> s + (t.price || 0), 0);
      if (selectedTestsList) {
        selectedTestsList.innerHTML = selectedTests.length
          ? selectedTests.map(t => `<li><span>${t.name}</span><strong>₹${t.price.toFixed(2)}</strong></li>`).join("")
          : "<li>No tests selected.</li>";
      }
      if (totalAmountSpan) totalAmountSpan.textContent = `₹${totalAmount.toFixed(2)}`;
      if (hiddenTestsInput) hiddenTestsInput.value = selectedTests.map(t=>t.id).join(",");
      const hiddenTotal = document.getElementById("total_amount_input");
      if (hiddenTotal) hiddenTotal.value = totalAmount.toFixed(2);
      const finalBtn = document.querySelector('button[name="generate_invoice"]');
      if (finalBtn) finalBtn.disabled = totalAmount === 0;
    }

    if (testCards.length){
      testCards.forEach(card => card.addEventListener("click", () => {
        const id = card.dataset.id, name = card.dataset.name, price = parseFloat(card.dataset.price) || 0;
        card.classList.toggle("selected");
        if (card.classList.contains("selected")) selectedTests.push({id, name, price});
        else selectedTests = selectedTests.filter(t => t.id !== id);
        updateSummary();
      }));
    }

    /* ===============================
       Payment / Cash calculation
       =============================== */
    const methodRadios = document.querySelectorAll('input[name="payment_method"]'),
          cashDetails = document.getElementById("cash-details"),
          cashReceived = document.getElementById("cash_received"),
          balanceSpan = document.getElementById("balance-amount");

    if (methodRadios.length){
      methodRadios.forEach(r => r.addEventListener("change", function(){
        if (cashDetails) cashDetails.style.display = this.value === "Cash" ? "block" : "none";
        if (this.value !== "Cash" && cashReceived){
          cashReceived.value = ""; if (balanceSpan) balanceSpan.textContent = "₹0.00";
        }
      }));
    }
    cashReceived?.addEventListener("input", function(){
      const received = parseFloat(this.value) || 0;
      const balance = received - totalAmount;
      if (balanceSpan) balanceSpan.textContent = `₹${balance.toFixed(2)}`;
    });

    /* ===============================
       Patient quick search (onboarding)
       =============================== */
    const patientSearch = document.getElementById("patient-search"),
          patientResults = document.getElementById("patient-search-results");

    patientSearch?.addEventListener("keyup", function(){
      const q = this.value;
      if (q.length < 2){ if (patientResults) patientResults.innerHTML = ""; return; }
      fetch(`ajax_search_patient.php?query=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
          let html = '<ul class="search-results-list">';
          if (Array.isArray(data) && data.length){
            data.forEach(p => {
              const age = new Date().getFullYear() - new Date(p.dob).getFullYear();
              html += `<li><div><strong>${p.name}</strong> (${age} yrs) - ${p.phone}</div><a href="create_bill.php?step=2&patient_id=${p.id}" class="btn btn-sm btn-primary">Select</a></li>`;
            });
          } else html += "<li>No patients found.</li>";
          html += "</ul>";
          if (patientResults) patientResults.innerHTML = html;
        });
    });

    /* ===============================
       Admins AJAX search
       =============================== */
    const adminSearch = document.getElementById("admin-search"),
          adminBranch = document.getElementById("admin-branch-filter"),
          adminsTbody = document.getElementById("admins-table-body");

    function performAdminSearch(){
      if (!adminsTbody) return;
      const q = adminSearch ? adminSearch.value.trim() : "";
      const b = adminBranch && adminBranch.value ? adminBranch.value : "";
      fetch(`ajax_search.php?page=users&query=${encodeURIComponent(q)}&branch_id=${encodeURIComponent(b)}`)
        .then(r => r.text())
        .then(html => {
          adminsTbody.innerHTML = html || "<tr><td colspan='4' style='text-align:center;'>No results.</td></tr>";
          const tbl = adminsTbody.closest('.responsive-table');
          if (tbl) applyMobileCards(tbl);
        });
    }
    adminSearch?.addEventListener("input", performAdminSearch);
    adminBranch?.addEventListener("change", performAdminSearch);
  });
})();
