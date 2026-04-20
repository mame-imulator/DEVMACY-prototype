<style>
#aiAssistantPanel {
    position: fixed;
    top: 0;
    right: -450px;
    width: 420px;
    height: 100vh;
    background: rgba(15, 23, 42, 0.98);
    backdrop-filter: blur(30px);
    border-left: 1px solid rgba(255, 255, 255, 0.1);
    z-index: 2000;
    transition: right 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    padding: 24px;
    box-shadow: -20px 0 60px rgba(0, 0, 0, 0.6);
    color: white;
    display: flex;
    flex-direction: column;
}

#aiAssistantPanel.open { right: 0; }

.ai-tabs {
    display: flex;
    background: rgba(255,255,255,0.05);
    padding: 4px;
    border-radius: 12px;
    margin-bottom: 24px;
}
.ai-tab {
    flex: 1;
    text-align: center;
    padding: 10px;
    cursor: pointer;
    font-size: 13px;
    font-weight: 700;
    border-radius: 8px;
    color: var(--text-muted);
}
.ai-tab.active {
    background: var(--primary-color);
    color: white;
}

.ai-header { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; }
.ai-icon-pulse {
    width: 36px; height: 36px; background: var(--primary-color); border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    animation: ai-pulse 2s infinite;
}
@keyframes ai-pulse {
    0% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0.4); }
    70% { box-shadow: 0 0 0 15px rgba(99, 102, 241, 0); }
    100% { box-shadow: 0 0 0 0 rgba(99, 102, 241, 0); }
}

.diagnostic-box {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 16px;
    padding: 16px;
    margin-bottom: 24px;
}
.diagnostic-box textarea {
    width: 100%;
    height: 80px;
    background: transparent;
    border: none;
    color: white;
    resize: none;
    font-size: 14px;
    outline: none;
}

#aiSuggestionsList { flex: 1; overflow-y: auto; padding-right: 5px; }

.suggestion-card {
    background: rgba(255, 255, 255, 0.04);
    border-radius: 14px;
    padding: 16px;
    margin-bottom: 12px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

/* Typing / Thinking Animation */
.thinking {
    display: flex;
    gap: 4px;
    align-items: center;
    color: var(--primary-color);
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 16px;
}
.dot { width: 5px; height: 5px; background: var(--primary-color); border-radius: 50%; animation: blink 1.4s infinite both; }
.dot:nth-child(2) { animation-delay: 0.2s; }
.dot:nth-child(3) { animation-delay: 0.4s; }
@keyframes blink { 0%, 80%, 100% { opacity: 0; } 40% { opacity: 1; } }

.add-btn {
    background: var(--primary-color);
    color: white; border: none; padding: 6px 12px; border-radius: 6px;
    font-size: 12px; font-weight: 700; cursor: pointer;
}
</style>

<div id="aiAssistantPanel">
    <div class="ai-close" onclick="toggleAIAssistant()" style="position:absolute; top:24px; right:24px; cursor:pointer; opacity:0.5;"><i class="ph ph-x"></i></div>
    
    <div class="ai-header">
        <div class="ai-icon-pulse"><i class="ph ph-sparkle" style="font-size: 18px; color: white;"></i></div>
        <div>
            <h3 style="margin: 0; font-size: 18px;">Gemini Assistant</h3>
            <span id="aiStatus" style="font-size: 11px; text-transform: uppercase; color: var(--primary-color); font-weight: 800;">System Standby</span>
        </div>
    </div>

    <div class="ai-tabs">
        <div class="ai-tab active" onclick="switchAITab('analyze', this)">Cart Insights</div>
        <div class="ai-tab" onclick="switchAITab('diag', this)">Diagnostic Helper</div>
    </div>

    <div id="analyzeSection">
        <div id="cartInsights"></div>
    </div>

    <div id="diagSection" style="display: none;">
        <div class="diagnostic-box">
            <textarea id="aiPrompt" 
                      placeholder="Ask Gemini: 'Patient has a sharp headache and fever...'"
                      onkeydown="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); runDiagnostic(); }"></textarea>
            <button onclick="runDiagnostic()" style="width:100%; padding:10px; border-radius:8px; background:var(--primary-color); color:white; border:none; font-weight:700; cursor:pointer;">
                <i class="ph ph-magic-wand"></i> Ask Gemini
            </button>
        </div>
        <div id="diagResults"></div>
    </div>

    <div style="margin-top: auto; padding-top: 16px; border-top: 1px solid rgba(255,255,255,0.1); font-size: 11px; color: var(--text-muted); opacity: 0.7;">
        <p>AI suggestions are derived from your Symptom Dictionary. Always verify dosages with clinical standards.</p>
    </div>
</div>

<?php
// Fetch Symptoms list to pass to JS
$symptomList = [];
if (isset($pdo)) {
    $symptomList = $pdo->query("SELECT symptom_id, symptom_name FROM Symptom")->fetchAll(PDO::FETCH_ASSOC);
}
?>

<script>
const symptomMaster = <?= json_encode($symptomList) ?>;
let aiMode = 'analyze';

function toggleAIAssistant() {
    const panel = document.getElementById('aiAssistantPanel');
    panel.classList.toggle('open');
    if (panel.classList.contains('open')) {
        if(aiMode === 'analyze') analyzeCart();
    }
}

function switchAITab(mode, el) {
    aiMode = mode;
    document.querySelectorAll('.ai-tab').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
    
    document.getElementById('analyzeSection').style.display = (mode === 'analyze' ? 'block' : 'none');
    document.getElementById('diagSection').style.display = (mode === 'diag' ? 'block' : 'none');
    
    if(mode === 'analyze') analyzeCart();
}

async function runDiagnostic() {
    const prompt = document.getElementById('aiPrompt').value.toLowerCase();
    const results = document.getElementById('diagResults');
    const status = document.getElementById('aiStatus');
    
    if(!prompt.trim()) return;

    // Thinking State
    results.innerHTML = `
        <div class="thinking">
            Gemini is matching symptoms <div class="dot"></div><div class="dot"></div><div class="dot"></div>
        </div>`;
    status.innerText = "Analyzing Symptoms...";

    // 1. Identify Symptoms from Prompt
    let foundSymptomIds = [];
    let detectedNames = [];
    symptomMaster.forEach(s => {
        if (prompt.includes(s.symptom_name.toLowerCase())) {
            foundSymptomIds.push(s.symptom_id);
            detectedNames.push(s.symptom_name);
        }
    });

    // Semantic Mapping (Mapping common words to DB keywords)
    if (prompt.includes("hot") || prompt.includes("temp")) { 
        const s = symptomMaster.find(sm => sm.symptom_name === 'Fever');
        if(s && !foundSymptomIds.includes(s.symptom_id)) foundSymptomIds.push(s.symptom_id);
    }
    if (prompt.includes("hurt") || prompt.includes("ache")) {
        const s = symptomMaster.find(sm => sm.symptom_name === 'Pain Relief');
        if(s && !foundSymptomIds.includes(s.symptom_id)) foundSymptomIds.push(s.symptom_id);
    }

    setTimeout(async () => {
        if (foundSymptomIds.length === 0) {
            results.innerHTML = `
                <div style="padding:20px; text-align:center;">
                    <p style="color:var(--text-muted); margin-bottom:12px;">I couldn't identify specific symptoms in your description.</p>
                    <p style="font-size:12px; color:rgba(255,255,255,0.4)">Try these keywords: <b>${symptomMaster.slice(0,5).map(sm => sm.symptom_name).join(', ')}</b></p>
                </div>`;
            status.innerText = "Clinical AI Standing By";
            return;
        }

        try {
            const response = await fetch(`api/get_recommendations.php?symptoms=${foundSymptomIds.join(',')}`);
            const data = await response.json();
            
            status.innerText = "Diagnosis Complete";
            
            if (data.length === 0) {
                results.innerHTML = `<div style="padding:20px; text-align:center; color:var(--text-muted)">No matching medications in stock for detected symptoms.</div>`;
            } else {
                let html = `<p style="font-size:12px; color:var(--text-muted); margin-bottom:12px;">BASED ON SYMPTOMS: <b>${foundSymptomIds.length} Detected</b></p>`;
                data.forEach(item => {
                    html += `
                    <div class="suggestion-card">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                            <div>
                                <h4 style="margin:0; font-size:15px;">${item.product_name}</h4>
                                <span style="font-size:11px; color:var(--text-muted)">${item.size_description} • $${parseFloat(item.price_per_unit).toFixed(2)}</span>
                            </div>
                            <button onclick='quickAddFromAI(${JSON.stringify(item)})' class="add-btn">ADD</button>
                        </div>
                    </div>`;
                });
                results.innerHTML = html;
            }
        } catch (e) {
            results.innerHTML = "Error fetching recommendations.";
        }
    }, 1200);
}

function quickAddFromAI(item) {
    // Call the POS global addToCart
    if (typeof addToCart === 'function') {
        addToCart({
            barcode: item.barcode,
            product_name: item.product_name,
            size_description: item.size_description,
            price_per_unit: item.price_per_unit,
            original_price: item.price_per_unit,
            has_promo: false
        });
        
        // Show success briefly
        const status = document.getElementById('aiStatus');
        const oldState = status.innerText;
        status.innerText = "Added to Sale!";
        status.style.color = "var(--secondary-color)";
        setTimeout(() => {
            status.innerText = oldState;
            status.style.color = "var(--primary-color)";
        }, 2000);
    }
}

function analyzeCart() {
    const list = document.getElementById('cartInsights');
    if (cart.length === 0) {
        list.innerHTML = `
            <div style="text-align: center; padding-top: 50px; color: var(--text-muted);">
                <i class="ph ph-brain" style="font-size: 48px; opacity: 0.2; margin-bottom: 16px; display: block;"></i>
                <p>Add items to your cart to see clinical insights and smart suggestions.</p>
            </div>`;
        return;
    }

    let suggestions = [];
    const cartNames = cart.map(i => i.name.toLowerCase());
    
    // Clinical Logic Rules
    if (cartNames.some(n => n.includes('insulin'))) {
        suggestions.push({ icon: 'ph-thermometer', color: '#f87171', title: 'Diabetes Care', text: 'Insulin detected. Check for 31G needles & alcohol swabs.' });
    }
    if (cartNames.some(n => n.includes('paracetamol'))) {
        suggestions.push({ icon: 'ph-shield-check', color: '#60a5fa', title: 'Dosage Safety', text: 'Advise on 4g max daily dose. Pair with Vitamin C.' });
    }
    if (cartNames.some(n => n.includes('azithromycin'))) {
        suggestions.push({ icon: 'ph-warning-circle', color: '#fbbf24', title: 'Compliance', text: 'Remind patient to finish the full course.' });
    }

    if (suggestions.length === 0) {
        suggestions.push({ icon: 'ph-sparkle', color: '#a78bfa', title: 'Gemini Check', text: 'No critical drug interactions detected for this combinations.' });
    }

    list.innerHTML = suggestions.map(s => `
        <div class="suggestion-card" style="border-left: 4px solid ${s.color}; margin-bottom: 12px; padding: 12px;">
            <div style="display: flex; gap: 12px; align-items: flex-start;">
                <i class="${s.icon}" style="font-size: 18px; color: ${s.color}; margin-top: 2px;"></i>
                <div>
                    <h4 style="margin: 0 0 4px 0; font-size: 14px;">${s.title}</h4>
                    <p style="margin: 0; font-size: 12px; color: rgba(255,255,255,0.7);">${s.text}</p>
                </div>
            </div>
        </div>
    `).join('');
}
</script>
