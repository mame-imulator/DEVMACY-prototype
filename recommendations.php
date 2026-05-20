<?php
// recommendations.php
$page_title = 'Clinical Symptom Helper';
include 'includes/header.php';
require_once 'includes/db.php';

// xCRUD for Symptom Management (Admin Only)
$symptom_manager_html = '';
$is_admin = (($_SESSION['role_name'] ?? '') === 'Admin');

if ($is_admin && file_exists('xcrud/xcrud.php')) {
    require_once ('xcrud/xcrud.php');
    $x_mgr = Xcrud::get_instance();
    $x_mgr->table('Symptom');
    $x_mgr->table_name('Manage Clinical Symptoms');
    $x_mgr->unset_print();
    $x_mgr->unset_csv();
    $symptom_manager_html = $x_mgr->render();
}

// Fetch all symptoms for the search UI
$stmt = $pdo->query("SELECT * FROM Symptom ORDER BY symptom_name ASC");
$symptomsArr = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Dictionary Data: Drugs and their associated Symptoms
$query = "
    SELECT p.product_name, GROUP_CONCAT(s.symptom_name SEPARATOR ', ') as symptoms
    FROM Product p
    LEFT JOIN Product_Symptom ps ON p.product_id = ps.product_id
    LEFT JOIN Symptom s ON ps.symptom_id = s.symptom_id
    GROUP BY p.product_id
    ORDER BY p.product_name ASC
";
$dictionary = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
?>

<style>
    /* Tab System */
    .clinical-tabs {
        display: flex;
        gap: 8px;
        margin-bottom: 24px;
        background: rgba(30, 41, 59, 0.5);
        padding: 6px;
        border-radius: var(--radius-md);
        width: fit-content;
    }
    .c-tab {
        padding: 10px 24px;
        border-radius: var(--radius-sm);
        color: var(--text-muted);
        cursor: pointer;
        font-weight: 600;
        transition: all 0.2s;
    }
    .c-tab.active {
        background: var(--primary-color);
        color: white;
        box-shadow: var(--shadow-glow);
    }

    .symptom-tag {
        padding: 10px 20px;
        border-radius: 30px;
        background: var(--surface-light);
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
        border: 1px solid var(--border-color);
        user-select: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .symptom-tag.active {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
        box-shadow: var(--shadow-glow);
    }
    .symptom-tag:hover:not(.active) {
        border-color: var(--primary-color);
        color: var(--text-main);
    }

    .recommendation-card {
        padding: 24px;
        transition: transform 0.2s;
    }
    .recommendation-card:hover {
        transform: translateY(-5px);
    }

    /* Dictionary Styles */
    .dictionary-item {
        padding: 20px;
        border-bottom: 1px solid var(--border-color);
        display: flex;
        gap: 24px;
        align-items: baseline;
    }
    .dictionary-item:last-child { border-bottom: none; }
    .dict-symptom {
        min-width: 180px;
        font-weight: 800;
        color: var(--primary-color);
        font-family: 'Outfit', sans-serif;
    }
    .dict-drugs {
        color: var(--text-main);
        font-size: 15px;
    }

    .stock-badge {
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
    }
    .stock-good { background: rgba(16, 185, 129, 0.1); color: var(--secondary-color); }
    .stock-low { background: rgba(244, 63, 94, 0.1); color: var(--accent-color); }
</style>

<div class="page-container">
    
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
        <div class="clinical-tabs">
            <div class="c-tab active" onclick="switchTab('search', this)">
                <i class="ph ph-magnifying-glass"></i> Smart Search
            </div>
            <div class="c-tab" onclick="switchTab('dict', this)">
                <i class="ph ph-book-open"></i> Clinical Dictionary
            </div>
        </div>

        <?php if($is_admin): ?>
            <button onclick="toggleManager()" class="btn" style="background: var(--surface-light); color: white; display: flex; align-items: center; gap: 8px;">
                <i class="ph ph-gear"></i> Manage Symptoms
            </button>
        <?php endif; ?>
    </div>

    <!-- Management Panel (Hidden by default) -->
    <?php if($is_admin && $symptom_manager_html): ?>
        <div id="manage-panel" class="glass-panel" style="display:none; padding: 24px; margin-bottom: 32px; border: 1px solid var(--primary-color);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h4 style="margin:0;">Symptom Catalog Editor</h4>
                <button onclick="toggleManager()" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><i class="ph ph-x" style="font-size:20px;"></i></button>
            </div>
            <div class="xcrud-wrapper">
                <?= $symptom_manager_html ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- TAB: SMART SEARCH -->
    <div id="tab-search" class="tab-content">
        <div class="glass-panel" style="padding: 32px; margin-bottom: 32px;">
            <h3 style="margin-bottom: 12px;">What are the patient's symptoms?</h3>
            <p style="color: var(--text-muted); margin-bottom: 24px;">Select multiple symptoms to find combined treatments.</p>
            
            <div id="symptom-container" style="display: flex; flex-wrap: wrap; gap: 12px;">
                <?php if(empty($symptomsArr)): ?>
                    <p style="color: var(--text-muted); font-style: italic;">No symptoms defined.</p>
                <?php else: ?>
                    <?php foreach($symptomsArr as $s): ?>
                        <div class="symptom-tag" data-id="<?= $s['symptom_id'] ?>" onclick="toggleSymptom(this)">
                            <i class="ph ph-plus-circle"></i>
                            <?= htmlspecialchars($s['symptom_name']) ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="results-section" style="display:none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
                <h4 id="results-title">Recommended Medicines</h4>
                <span id="results-count" style="color: var(--text-muted); font-size: 14px;"></span>
            </div>
            
            <div id="recommendations-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 24px;">
                <!-- Dynamic Results -->
            </div>
        </div>

        <div id="empty-state" style="padding: 80px 0; text-align: center; border: 2px dashed var(--border-color); border-radius: var(--radius-lg);">
            <i class="ph ph-stethoscope" style="font-size: 64px; color: var(--text-muted); margin-bottom: 16px;"></i>
            <h4 style="color: var(--text-muted);">No symptoms selected</h4>
            <p style="color: var(--text-muted); margin-top: 8px;">Select symptoms above to see recommendations.</p>
        </div>
    </div>

    <!-- TAB: CLINICAL DICTIONARY -->
    <div id="tab-dict" class="tab-content" style="display:none;">
        <div class="glass-panel" style="padding: 0; overflow: hidden;">
            <div style="padding: 24px; border-bottom: 1px solid var(--border-color); background: rgba(255,255,255,0.02);">
                <h3 style="margin:0;">A-Z Clinical Encyclopedia</h3>
                <p style="color: var(--text-muted); font-size: 13px; margin-top: 4px;">Alphabetical list of symptoms and their associated treatments.</p>
            </div>
            
            <div class="dictionary-list">
                <?php if(empty($dictionary)): ?>
                    <div style="padding: 40px; text-align: center; color: var(--text-muted);">No data available.</div>
                <?php else: ?>
                    <?php foreach($dictionary as $d): ?>
                        <div class="dictionary-item">
                            <div class="dict-symptom"><?= htmlspecialchars($d['product_name']) ?></div>
                            <div class="dict-drugs"><?= $d['symptoms'] ? htmlspecialchars($d['symptoms']) : '<i style="color:var(--text-muted)">No symptoms linked yet</i>' ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<script>
let selectedSymptoms = [];

function switchTab(tabId, el) {
    // Hide all contents
    document.querySelectorAll('.tab-content').forEach(tc => tc.style.display = 'none');
    // Deactivate all tabs
    document.querySelectorAll('.c-tab').forEach(t => t.classList.remove('active'));
    
    // Show selected
    document.getElementById('tab-' + tabId).style.display = 'block';
    el.classList.add('active');
}

function toggleManager() {
    const panel = document.getElementById('manage-panel');
    panel.style.display = (panel.style.display === 'none') ? 'block' : 'none';
    if (panel.style.display === 'block') {
        panel.scrollIntoView({ behavior: 'smooth' });
    }
}

function toggleSymptom(el) {
    const id = el.getAttribute('data-id');
    const index = selectedSymptoms.indexOf(id);
    
    if (index > -1) {
        selectedSymptoms.splice(index, 1);
        el.classList.remove('active');
        el.querySelector('i').className = 'ph ph-plus-circle';
    } else {
        selectedSymptoms.push(id);
        el.classList.add('active');
        el.querySelector('i').className = 'ph ph-check-circle';
    }
    
    updateRecommendations();
}

async function updateRecommendations() {
    const resultsGrid = document.getElementById('recommendations-grid');
    const resultsSection = document.getElementById('results-section');
    const emptyState = document.getElementById('empty-state');
    
    if (selectedSymptoms.length === 0) {
        resultsSection.style.display = 'none';
        emptyState.style.display = 'block';
        return;
    }
    
    try {
        const response = await fetch(`api/get_recommendations.php?symptoms=${selectedSymptoms.join(',')}`);
        const data = await response.json();
        
        resultsGrid.innerHTML = '';
        resultsSection.style.display = 'block';
        emptyState.style.display = 'none';
        
        document.getElementById('results-count').innerText = `${data.length} medicines found`;

        if (data.length === 0) {
            resultsGrid.innerHTML = `
                <div style="grid-column: 1/-1; padding: 40px; text-align: center; color: var(--text-muted);">
                    <p>No drugs found matching ALL selected symptoms. Try refining your selection.</p>
                </div>
            `;
            return;
        }

        data.forEach(item => {
            const stockLevel = item.total_stock || 0;
            const stockClass = stockLevel > 10 ? 'stock-good' : 'stock-low';
            
            const card = `
                <div class="glass-panel recommendation-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                        <div>
                            <h4 style="margin:0;">${item.product_name}</h4>
                            <span style="font-size: 11px; color: var(--text-muted); font-family: monospace; display: block; margin-top: 2px;">
                                [ID: #${item.product_id} | Barcode: ${item.barcode}]
                            </span>
                        </div>
                        <span class="stock-badge ${stockClass}">${stockLevel} in stock</span>
                    </div>
                    <p style="color: var(--text-muted); font-size: 14px; margin-bottom: 20px;">
                        <i class="ph ph-package"></i> Pack: ${item.size_description}
                    </p>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--border-color); padding-top: 16px;">
                        <div>
                            <span style="font-size: 12px; color: var(--text-muted); display: block;">Price</span>
                            <span style="font-size: 18px; font-weight: 700; color: var(--secondary-color);">$${parseFloat(item.price_per_unit).toFixed(2)}</span>
                        </div>
                        <a href="pos.php?search=${encodeURIComponent(item.product_name)}" class="btn-primary" style="padding: 8px 16px; text-decoration: none; font-size: 14px; display: flex; align-items: center; gap: 6px;">
                            <i class="ph ph-shopping-cart"></i> Go to POS
                        </a>
                    </div>
                </div>
            `;
            resultsGrid.innerHTML += card;
        });
        
    } catch (e) {
        console.error(e);
    }
}
</script>

<?php include 'includes/footer.php'; ?>

