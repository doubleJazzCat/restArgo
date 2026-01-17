<?php
// restArgo v1.10 Frontend
// Fix: UI Input Borders (Gray default, Black focus)
error_reporting(0);
session_start();

$CONFIG = [];
if (file_exists('config.php')) {
    $temp = require 'config.php';
    if (is_array($temp)) $CONFIG = $temp;
}
$UI_PASS = $CONFIG['UI_ACCESS_PASSWORD'] ?? '';
$BASE_URL = $CONFIG['BASE_URL'] ?? '';
if (!empty($BASE_URL) && substr($BASE_URL, -1) !== '/') $BASE_URL .= '/';

if (!empty($UI_PASS)) {
    if (isset($_POST['unlock_pass']) && $_POST['unlock_pass'] === $UI_PASS) {
        $_SESSION['restargo_unlocked'] = true;
        header("Location: " . $_SERVER['PHP_SELF']); exit;
    }
    if (!isset($_SESSION['restargo_unlocked'])) {
        ?>
        <!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Locked</title><?php if ($BASE_URL): ?><base href="<?php echo htmlspecialchars($BASE_URL); ?>"><?php endif; ?></head><body style="background:#f3f4f6;height:100vh;display:flex;align-items:center;justify-content:center;font-family:sans-serif;margin:0;"><form method="POST" style="background:white;padding:30px;border-radius:8px;box-shadow:0 4px 6px rgba(0,0,0,0.1);text-align:center;width:300px;"><h3 style="margin-top:0;color:#333;">🔒 restArgo</h3><input type="password" name="unlock_pass" placeholder="Password" style="padding:10px;border:1px solid #ddd;border-radius:4px;width:100%;box-sizing:border-box;margin-bottom:15px;display:block;"><button type="submit" style="background:#2563eb;color:white;border:none;padding:10px 20px;border-radius:4px;cursor:pointer;width:100%;font-weight:bold;">Unlock</button></form></body></html>
        <?php exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>restArgo</title>
    <?php if ($BASE_URL): ?><base href="<?php echo htmlspecialchars($BASE_URL); ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/highlight.css">
    <script src="assets/vue.js" data-cfasync="false"></script>
    <script src="assets/tailwind.js" data-cfasync="false"></script>
    <script src="assets/beautify.js" data-cfasync="false"></script>
    <script src="assets/beautify-html.js" data-cfasync="false"></script>
    <script src="assets/highlight.js" data-cfasync="false"></script>
    <script src="assets/sortable.js" data-cfasync="false"></script>
    <style>
        body { background-color: #f3f4f6; color: #1f2937; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; margin: 0; }
        .tab-active { border-bottom: 2px solid #3b82f6; color: #3b82f6; font-weight: 600; }
        .sidebar-tab-active { background-color: white; color: #2563eb; border-top: 2px solid #2563eb; font-weight: 700; }
        .sidebar-tab-inactive { background-color: #f9fafb; color: #6b7280; font-weight: 500; }
        .preview-frame { background: white; width: 100%; height: 100%; border: none; }
        [v-cloak] { display: none; }
        pre code.hljs { display: block; overflow-x: auto; padding: 1em; background: white; font-family: Consolas, Monaco, monospace; font-size: 12px; line-height: 1.5; border: none; height: 100%; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f3f4f6; }
        ::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 4px; }
        .sortable-ghost { opacity: 0.4; background-color: #e5e7eb; }
        .folder-drag-over { background-color: #dbeafe; border: 1px dashed #3b82f6; }
        /* 通用输入框样式修复 */
        input:focus, select:focus, textarea:focus { outline: none; }
    </style>
</head>
<body class="h-screen flex overflow-hidden">
<div id="app" class="flex w-full h-full relative" v-cloak>
    
    <div v-if="showSaveModal" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-80 p-4">
            <h3 class="font-bold text-gray-700 mb-3">Save Request</h3>
            <label class="block text-xs font-bold text-gray-500 mb-1">Name</label>
            <input v-model="saveForm.name" class="w-full border border-gray-300 focus:border-black p-2 rounded text-sm mb-3" placeholder="Request Name">
            <label class="block text-xs font-bold text-gray-500 mb-1">Folder</label>
            <select v-model="saveForm.folderId" class="w-full border border-gray-300 focus:border-black p-2 rounded text-sm mb-4 bg-white">
                <option :value="0">/ (Root)</option>
                <option v-for="f in folders" :key="f.id" :value="f.id">📂 {{ f.name }}</option>
            </select>
            <div class="flex justify-end space-x-2">
                <button @click="showSaveModal=false" class="px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100 rounded">Cancel</button>
                <button @click="confirmSave" class="px-3 py-1.5 text-sm bg-blue-600 text-white rounded font-bold hover:bg-blue-700">Save</button>
            </div>
        </div>
    </div>

    <div v-if="deleteModal.show" class="fixed inset-0 bg-black bg-opacity-40 flex items-center justify-center z-50">
        <div class="bg-white rounded p-4 w-96 shadow-xl">
            <h3 class="font-bold text-red-600 mb-2">Delete Folder?</h3>
            <p class="text-sm text-gray-600 mb-4">"{{ deleteModal.name }}" may contain items.</p>
            <div class="flex flex-col space-y-2">
                <button @click="confirmDeleteFolder('move_root')" class="p-2 border rounded hover:bg-gray-50 text-sm text-left">📂 Move contents to Root (Safe)</button>
                <button @click="confirmDeleteFolder('delete_all')" class="p-2 border border-red-200 rounded hover:bg-red-50 text-sm text-left text-red-600">💥 Delete Everything</button>
                <button @click="deleteModal.show=false" class="p-2 text-sm text-gray-500 hover:underline">Cancel</button>
            </div>
        </div>
    </div>

    <div class="w-64 flex flex-col border-r border-gray-200 bg-white">
        <div class="flex border-b border-gray-200 bg-gray-50">
            <button @click="sidebarTab='history'" :class="sidebarTab==='history' ? 'sidebar-tab-active' : 'sidebar-tab-inactive'" class="flex-1 p-3 text-xs transition-colors">History</button>
            <button @click="sidebarTab='collections'" :class="sidebarTab==='collections' ? 'sidebar-tab-active' : 'sidebar-tab-inactive'" class="flex-1 p-3 text-xs transition-colors">Saved</button>
        </div>
        <div class="flex-1 overflow-y-auto bg-gray-50">
            
            <div v-if="sidebarTab==='history'" class="p-0">
                 <div v-if="history.length===0" class="p-4 text-xs text-center text-gray-500">No history yet.</div>
                 <div v-for="(item, idx) in history" :key="item.id || idx" class="p-3 border-b border-gray-200 bg-white group relative">
                    <div @click="loadRequest(item)" class="cursor-pointer">
                        <div class="flex items-center mb-1"><span :class="methodColor(item.method)" class="font-bold w-12 text-xs">{{ item.method }}</span><span class="text-gray-400 text-[10px] ml-auto">{{ formatTime(item.created_at) }}</span></div>
                        <div class="text-gray-600 truncate text-xs" :title="item.url">{{ item.url }}</div>
                    </div>
                    <button @click.stop="deleteHistory(item.id)" class="absolute top-1 right-1 text-gray-400 hover:text-red-500 font-bold opacity-0 group-hover:opacity-100 p-1 text-xs">×</button>
                </div>
                <div v-if="history.length > 0" class="p-2"><button @click="clearHistory" class="w-full text-xs text-red-400 hover:text-red-600 py-1">Clear All</button></div>
            </div>
            
            <div v-else class="p-2">
                <div class="flex justify-between items-center mb-2 px-1">
                    <span class="text-xs font-bold text-gray-400 uppercase">Folders</span>
                    <button @click="startCreate(0)" class="text-xs text-blue-600 hover:underline">+ New</button>
                </div>
                <div ref="rootSortableRef" class="space-y-1 min-h-[100px]" data-id="0" data-type="folder">
                    <div v-if="creatingFolderId === 0" class="flex items-center px-1 py-1 bg-blue-50 border border-blue-200 rounded mb-1">
                        <span class="text-xs mr-1">📂</span>
                        <input v-focus class="text-xs bg-transparent border-none outline-none w-full text-blue-700 placeholder-blue-300" placeholder="Folder Name..." @blur="cancelCreate" @keyup.enter="confirmCreate($event.target.value)" @keyup.esc="cancelCreate">
                    </div>
                    <tree-item v-for="node in folderTree" :key="node.uniqueKey" :model="node" :creating-id="creatingFolderId" @select="loadRequest" @del-col="deleteCollection" @del-fol="promptDeleteFolder" @start-create="startCreate" @cancel-create="cancelCreate" @confirm-create="confirmCreate"></tree-item>
                </div>
                <button @click="openSaveModal" class="w-full mt-4 text-blue-600 text-xs border border-blue-200 bg-blue-50 rounded p-2 font-bold hover:bg-blue-100">+ Save Current</button>
            </div>
        </div>
        
        <div class="px-4 py-2 bg-gray-50 border-t border-gray-200">
            <div class="flex justify-between text-[10px] text-gray-500 mb-1"><span>Storage</span><span>{{ storageStats.text }}</span></div>
            <div class="w-full bg-gray-200 rounded-full h-1.5 overflow-hidden"><div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500" :style="{ width: storageStats.percent + '%' }" :class="{'bg-red-500': storageStats.percent > 90}"></div></div>
        </div>
    </div>

    <div class="flex-1 flex flex-col min-w-0 bg-white">
        <div class="p-3 border-b border-gray-200 flex space-x-2 bg-white shadow-sm z-10">
            <select v-model="req.method" class="w-28 p-2 rounded-md font-bold text-sm bg-gray-50 border border-gray-300 focus:border-black"><option>GET</option><option>POST</option><option>PUT</option><option>DELETE</option><option>PATCH</option><option>HEAD</option><option>OPTIONS</option></select>
            <input v-model="req.url" @input="onUrlChange" placeholder="Enter request URL" class="flex-1 p-2 rounded-md text-sm font-mono bg-white border border-gray-300 focus:border-black">
            <button @click="sendRequest" :disabled="loading" class="bg-blue-600 hover:bg-blue-700 text-white px-6 rounded-md font-bold text-sm flex items-center shadow-sm disabled:opacity-50"><span v-if="loading" class="animate-spin mr-2">⟳</span> {{ loading ? 'Sending...' : 'Send' }}</button>
        </div>

        <div class="flex-1 flex flex-col min-h-0">
             <div class="h-1/2 flex flex-col border-b border-gray-200">
                <div class="flex border-b border-gray-200 px-4 bg-gray-50">
                    <button v-for="t in ['Params', 'Headers', 'Body', 'Auth', 'Config']" @click="activeTab=t" :class="{'tab-active': activeTab===t}" class="px-4 py-2 text-sm text-gray-600 font-medium relative hover:text-gray-800">{{ t }}</button>
                </div>
                <div class="flex-1 overflow-y-auto p-4 bg-white">
                    <div v-if="['Params', 'Headers'].includes(activeTab)">
                        <div v-for="(item, idx) in (activeTab==='Params'?req.params:req.headers)" class="flex space-x-2 mb-2 group"><input v-model="item.key" @input="activeTab==='Params'?updateUrlFromParams():null" placeholder="Key" class="flex-1 p-2 text-sm rounded border border-gray-300 focus:border-black font-mono"><input v-model="item.value" @input="activeTab==='Params'?updateUrlFromParams():null" placeholder="Value" class="flex-1 p-2 text-sm rounded border border-gray-300 focus:border-black font-mono"><button @click="removeItem(activeTab, idx)" class="text-gray-400 hover:text-red-500 px-2 opacity-0 group-hover:opacity-100">×</button></div>
                        <button @click="addItem(activeTab)" class="text-sm text-blue-600 font-bold hover:text-blue-800 flex items-center mt-2">+ Add entry</button>
                    </div>
                    <div v-if="activeTab==='Auth'" class="max-w-md">
                        <select v-model="authType" class="p-2 text-sm rounded border border-gray-300 focus:border-black mb-4 w-full bg-white"><option value="none">No Auth</option><option value="bearer">Bearer Token</option><option value="basic">Basic Auth</option></select>
                        <div v-if="authType==='bearer'"><input v-model="authToken" placeholder="Token" class="w-full p-2 text-sm rounded border border-gray-300 focus:border-black font-mono"></div>
                        <div v-if="authType==='basic'" class="flex space-x-3"><input v-model="authUser" placeholder="Username" class="flex-1 p-2 text-sm rounded border border-gray-300 focus:border-black"><input v-model="authPass" type="password" placeholder="Password" class="flex-1 p-2 text-sm rounded border border-gray-300 focus:border-black"></div>
                    </div>
                    <div v-if="activeTab==='Body'" class="h-full flex flex-col">
                        <div class="flex space-x-6 mb-3 text-sm font-medium text-gray-700"><label class="flex items-center"><input type="radio" v-model="bodyType" value="raw" class="mr-2"> Raw</label><label class="flex items-center"><input type="radio" v-model="bodyType" value="form" class="mr-2"> Form-Data</label></div>
                        <textarea v-if="bodyType==='raw'" v-model="req.body" class="flex-1 w-full p-3 bg-white text-sm font-mono rounded border border-gray-300 focus:border-black resize-none shadow-sm" placeholder="Request body..."></textarea>
                        <div v-if="bodyType==='form'"><div v-for="(item, idx) in req.formData" class="flex space-x-2 mb-2 group"><input v-model="item.key" placeholder="Key" class="flex-1 p-2 text-sm rounded border border-gray-300 focus:border-black font-mono"><input v-model="item.value" placeholder="Value" class="flex-1 p-2 text-sm rounded border border-gray-300 focus:border-black font-mono"><button @click="req.formData.splice(idx,1)" class="text-gray-400 hover:text-red-500 px-2 opacity-0 group-hover:opacity-100">×</button></div><button @click="req.formData.push({key:'',value:''})" class="text-sm text-blue-600 font-bold hover:text-blue-800">+ Add field</button></div>
                    </div>
                    <div v-if="activeTab==='Config'" class="max-w-md">
                        <label class="block text-xs font-bold text-gray-700 mb-1">Timeout (seconds)</label>
                        <input v-model="req.timeout" type="number" class="w-full p-2 text-sm rounded border border-gray-300 focus:border-black font-mono mb-4" placeholder="60">
                        <div class="text-xs text-gray-500">Max execution time for this request. Default is 60s.</div>
                    </div>
                </div>
            </div>
            
            <div class="flex-1 flex flex-col min-h-0 bg-white">
                <div class="flex justify-between items-center px-4 py-2 bg-gray-50 border-b border-gray-200">
                    <div class="flex space-x-2 items-center">
                        <span class="text-sm font-bold text-gray-700 mr-2">Response</span>
                        <div class="flex bg-gray-200 p-0.5 rounded-md">
                            <button v-for="m in ['Raw', 'Pretty', 'Preview', 'Inspect']" @click="resMode=m" :class="{'bg-white text-blue-600 shadow-sm': resMode===m, 'text-gray-600 hover:text-gray-800': resMode!==m}" class="px-3 py-1 text-xs font-medium rounded-[4px] transition-all">{{ m }}</button>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4"><div v-if="res.status" class="flex space-x-4 text-xs font-mono font-medium"><span :class="statusColor(res.status)">{{ res.status }}</span><span class="text-gray-500">{{ res.time }} ms</span><span class="text-gray-500">{{ formatSize(res.size) }}</span></div><button v-if="res.body" @click="downloadResponse" class="text-xs flex items-center text-gray-600 hover:text-blue-600 font-medium">Download</button></div>
                </div>
                <div class="relative flex-1 overflow-hidden bg-white">
                    <div v-if="loading" class="absolute inset-0 flex items-center justify-center text-gray-500 flex-col"><span class="animate-spin text-2xl mb-2">⟳</span><span class="text-sm font-medium">Sending...</span></div>
                    <div v-else-if="res.error" class="p-4 text-red-500 text-sm font-mono whitespace-pre-wrap bg-red-50 h-full">{{ res.error }}</div>
                    <div v-else-if="res.body" class="w-full h-full relative">
                        <div v-if="resMode==='Preview'" class="w-full h-full bg-gray-100 flex items-center justify-center overflow-auto p-4"><img v-if="isImage" :src="previewDataUrl" class="max-w-full max-h-full object-contain shadow-md bg-white-canvas"><iframe v-else :srcdoc="previewHtml" class="preview-frame bg-white shadow-sm rounded"></iframe></div>
                        <div v-else-if="resMode==='Pretty'" class="w-full h-full bg-white overflow-auto relative"><pre class="m-0 h-full"><code class="hljs h-full" v-html="highlightedBody"></code></pre></div>
                        <textarea v-else-if="resMode==='Raw'" :value="decodedBody" readonly class="w-full h-full p-4 bg-white text-gray-800 font-mono text-xs resize-none outline-none border-none"></textarea>
                        <div v-else-if="resMode==='Inspect'" class="w-full h-full flex flex-col p-4 space-y-4 overflow-auto bg-gray-50">
                            <div class="bg-white border rounded shadow-sm"><div class="bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600 border-b">Request Headers (Actual)</div><pre class="p-3 text-xs font-mono text-gray-700 whitespace-pre-wrap">{{ res.reqHeaders || 'No data' }}</pre></div>
                            <div class="bg-white border rounded shadow-sm"><div class="bg-gray-100 px-3 py-2 text-xs font-bold text-gray-600 border-b">Response Headers</div><pre class="p-3 text-xs font-mono text-blue-800 whitespace-pre-wrap">{{ res.resHeaders || 'No data' }}</pre></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const { createApp, ref, computed, onMounted, nextTick } = Vue;

const TreeItem = {
    name: 'tree-item',
    props: ['model', 'creatingId'],
    directives: { focus: { mounted: (el) => el.focus() } },
    template: `
    <div :data-id="model.id" :data-type="model.isFolder ? 'folder' : 'collection'" class="select-none">
        <div v-if="model.isFolder" class="mb-1">
            <div class="flex items-center group px-1 py-1 hover:bg-gray-100 rounded cursor-pointer" @click="toggle">
                <span class="mr-1 text-xs transform transition-transform" :class="{'rotate-90': isOpen}">▶</span>
                <span class="text-xs mr-1">📁</span>
                <span class="font-bold text-xs text-gray-700 flex-1 truncate">{{ model.name }}</span>
                <button @click.stop="$emit('start-create', model.id)" class="text-blue-500 hover:text-blue-700 font-bold text-xs mr-2 opacity-0 group-hover:opacity-100" title="New Subfolder">＋</button>
                <button @click.stop="$emit('del-fol', model)" class="text-red-500 hover:text-red-700 font-bold text-xs opacity-0 group-hover:opacity-100" title="Delete">×</button>
            </div>
            <div v-show="isOpen" class="pl-3 border-l border-gray-300 ml-1.5 sortable-list min-h-[5px]" :data-id="model.id">
                <div v-if="creatingId === model.id" class="flex items-center px-1 py-1 bg-blue-50 border border-blue-200 rounded mb-1">
                    <span class="text-xs mr-1">📂</span>
                    <input v-focus class="text-xs bg-transparent border-none outline-none w-full text-blue-700 placeholder-blue-300" placeholder="Folder Name..." @blur="$emit('cancel-create')" @keyup.enter="$emit('confirm-create', $event.target.value)" @keyup.esc="$emit('cancel-create')">
                </div>
                <tree-item v-for="child in model.children" :key="child.uniqueKey" :model="child" :creating-id="creatingId" @select="$emit('select', $event)" @del-col="$emit('del-col', $event)" @del-fol="$emit('del-fol', $event)" @start-create="$emit('start-create', $event)" @cancel-create="$emit('cancel-create')" @confirm-create="$emit('confirm-create', $event)"></tree-item>
            </div>
        </div>
        <div v-else class="bg-white border border-gray-200 rounded shadow-sm mb-1 group relative hover:border-blue-400 ml-1">
            <div @click="$emit('select', model)" class="p-2 cursor-pointer">
                <div class="font-bold text-gray-800 text-xs mb-0.5">{{ model.name }}</div>
                <div class="flex items-center"><span :class="methodColor(model.method)" class="font-bold text-[10px] w-8">{{ model.method }}</span></div>
            </div>
            <button @click.stop="$emit('del-col', model.id)" class="absolute top-0 right-0 p-1 text-red-400 hover:text-red-600 font-bold opacity-0 group-hover:opacity-100 text-xs">×</button>
        </div>
    </div>
    `,
    setup(props) {
        const isOpen = ref(true); 
        const toggle = () => isOpen.value = !isOpen.value;
        const methodColor = (m) => ({'GET':'text-green-600','POST':'text-yellow-600','DELETE':'text-red-600','PUT':'text-blue-600'}[m] || 'text-gray-600');
        return { isOpen, toggle, methodColor };
    }
};

createApp({
    components: { 'tree-item': TreeItem },
    directives: { focus: { mounted: (el) => el.focus() } },
    setup() {
        const serverConfig = { defaultUrl: "api.php" }; 
        const config = ref({ apiUrl: '' }); 
        
        const folders = ref([]); const collections = ref([]); const history = ref([]); const storageStats = ref({ used: 0, limit: 0, percent: 0, text: 'Loading...' });
        const sidebarTab = ref('history'); const activeTab = ref('Params'); 
        const resMode = ref('Raw'); const bodyType = ref('raw'); const loading = ref(false);
        const req = ref({ method: 'GET', url: '', params: [{key:'',value:''}], headers: [{key:'',value:''}], body: '', formData: [{key:'',value:''}], timeout: 60 });
        const authType = ref('none'); const authToken = ref(''); const authUser = ref(''); const authPass = ref('');
        const res = ref({});
        const showSaveModal = ref(false); const saveForm = ref({ name: '', folderId: 0 }); const deleteModal = ref({ show: false, id: 0, name: '' });
        const creatingFolderId = ref(null);

        const methodColor = (m) => ({'GET':'text-green-600','POST':'text-yellow-600','DELETE':'text-red-600','PUT':'text-blue-600'}[m] || 'text-gray-600');
        const statusColor = (s) => s>=200&&s<300 ? 'text-green-600':'text-red-600';
        const formatTime = (ts) => new Date(ts * 1000).toLocaleTimeString();
        const formatSize = (bytes) => { if (!bytes) return '0 B'; const k = 1024; const sizes = ['B', 'KB', 'MB', 'GB']; const i = Math.floor(Math.log(bytes) / Math.log(k)); return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]; };

        const apiCall = async (action, data = {}, method = 'GET') => {
            const targetUrl = config.value.apiUrl || serverConfig.defaultUrl;
            const url = `${targetUrl}?action=${action}${method === 'DELETE' ? '&id='+data.id : ''}`;
            const opts = { method: method, headers: { 'Content-Type': 'application/json' } };
            if (method === 'POST') opts.body = JSON.stringify(data);
            const r = await fetch(url, opts);
            const text = await r.text();
            try { return JSON.parse(text); } catch(e) { return { error: 'Invalid JSON Response: ' + text.substring(0, 100) }; }
        };

        const fetchData = async () => { try { const [fData, cData] = await Promise.all([apiCall('folder_list'), apiCall('collection_list')]); if(Array.isArray(fData)) folders.value = fData; if(Array.isArray(cData)) collections.value = cData.map(i => ({...i, fullReq: JSON.parse(i.req_data || '{}')})); nextTick(() => initSortable()); fetchStorageStats(); } catch(e) {} };
        const fetchHistory = async () => { try { const data = await apiCall('history_list'); if(Array.isArray(data)) history.value = data.map(i => ({...i, fullReq: JSON.parse(i.req_data || '{}')})); } catch(e){} };
        const fetchStorageStats = async () => { try { const data = await apiCall('storage_stats'); if(data.limit) storageStats.value = { used: data.used, limit: data.limit, percent: data.percent, text: `${formatSize(data.used)} / ${data.limit_str}` }; } catch(e) {} };
        const folderTree = computed(() => { const map = {}; const roots = []; folders.value.forEach(f => { map[f.id] = { ...f, isFolder: true, children: [], uniqueKey: 'f-'+f.id }; }); folders.value.forEach(f => { if (f.parent_id && map[f.parent_id]) { map[f.parent_id].children.push(map[f.id]); } else { roots.push(map[f.id]); } }); collections.value.forEach(c => { const item = { ...c, isFolder: false, uniqueKey: 'c-'+c.id }; if (c.folder_id && map[c.folder_id]) { map[c.folder_id].children.push(item); } else { roots.push(item); } }); return roots; });
        const initSortable = () => { if (typeof Sortable === 'undefined') return; const containers = document.querySelectorAll('.sortable-list, [data-id="0"]'); containers.forEach(el => { if(el._sortable) return; new Sortable(el, { group: 'nested', animation: 150, fallbackOnBody: true, swapThreshold: 0.65, onEnd: async (evt) => { const type = evt.item.getAttribute('data-type'); const id = evt.item.getAttribute('data-id'); const targetId = evt.to.getAttribute('data-id'); await apiCall('item_move', { type, id, target_id: targetId }, 'POST'); fetchData(); } }); el._sortable = true; }); };

        const sendRequest = async () => {
            if (!req.value.url) return; loading.value = true; res.value = {};
            const tempItem = { id: 'temp-'+Date.now(), method: req.value.method, url: req.value.url, fullReq: JSON.parse(JSON.stringify(req.value)), created_at: Math.floor(Date.now()/1000) };
            history.value.unshift(tempItem); if(history.value.length > 100) history.value.pop();
            let authHeader = null; if (authType.value === 'bearer' && authToken.value) authHeader = `Bearer ${authToken.value}`; if (authType.value === 'basic' && authUser.value) authHeader = `Basic ${btoa(authUser.value + ':' + authPass.value)}`; const finalHeaders = []; req.value.headers.forEach(h => { if(h.key) finalHeaders.push(`${h.key}: ${h.value}`); }); if (authHeader) finalHeaders.push(`Authorization: ${authHeader}`); let finalBody = req.value.body; if (bodyType.value === 'form') { const params = new URLSearchParams(); req.value.formData.forEach(f => { if(f.key) params.append(f.key, f.value); }); finalBody = params.toString(); if (!finalHeaders.some(h => h.toLowerCase().includes('content-type'))) finalHeaders.push('Content-Type: application/x-www-form-urlencoded'); }
            try {
                const data = await apiCall('proxy', { url: req.value.url, method: req.value.method, headers: finalHeaders, body: finalBody, timeout: req.value.timeout }, 'POST');
                res.value = data;
                tempItem.res_status = data.status; tempItem.res_time = data.time; tempItem.res_size = data.size; tempItem.res_type = data.contentType; tempItem.res_body = data.body;
                tempItem.req_headers = data.reqHeaders; tempItem.res_headers = data.resHeaders;
                apiCall('history_add', { method: req.value.method, url: req.value.url, fullReq: req.value, res_status: data.status, res_time: data.time, res_size: data.size, res_type: data.contentType, res_body: data.body, req_headers: data.reqHeaders, res_headers: data.resHeaders }, 'POST').then(r => { if(r.id) tempItem.id = r.id; });
            } catch (e) { res.value = { error: 'Error: ' + e.message }; } finally { loading.value = false; }
        };

        const confirmSave = async () => {
            if(!saveForm.value.name) return;
            const payload = { name: saveForm.value.name, folder_id: saveForm.value.folderId, method: req.value.method, url: req.value.url, fullReq: req.value };
            if (res.value && res.value.status) { payload.res_status = res.value.status; payload.res_time = res.value.time; payload.res_size = res.value.size; payload.res_type = res.value.contentType; payload.res_body = res.value.body; payload.req_headers = res.value.reqHeaders; payload.res_headers = res.value.resHeaders; }
            const tempItem = { ...payload, id: 'temp-'+Date.now(), isFolder: false };
            collections.value.push(tempItem); showSaveModal.value = false;
            await apiCall('collection_add', payload, 'POST'); fetchData();
        };

        const loadRequest = (item) => { 
            req.value = JSON.parse(JSON.stringify(item.fullReq)); 
            if (item.res_status) { res.value = { status: item.res_status, time: item.res_time, size: item.res_size, contentType: item.res_type, body: item.res_body, isBase64: true, reqHeaders: item.req_headers, resHeaders: item.res_headers }; } else { res.value = {}; }
        };

        const confirmDeleteFolder = async (mode) => { const id = deleteModal.value.id; folders.value = folders.value.filter(f => f.id !== id); await apiCall('folder_delete', { id, mode }, 'POST'); deleteModal.value.show = false; fetchData(); };
        const deleteCollection = async (id) => { collections.value = collections.value.filter(c => c.id !== id); await apiCall('collection_delete', {id}, 'DELETE'); };
        const deleteHistory = async (id) => { history.value = history.value.filter(h => h.id !== id); await apiCall('history_delete', {id}, 'DELETE'); };
        const startCreate = (parentId) => { creatingFolderId.value = parentId; }; const cancelCreate = () => { creatingFolderId.value = null; };
        const confirmCreate = async (name) => { if (!name || !name.trim()) return cancelCreate(); const pid = creatingFolderId.value; creatingFolderId.value = null; await apiCall('folder_add', { name: name.trim(), parent_id: pid }, 'POST'); fetchData(); };
        const openSaveModal = () => { saveForm.value = { name: '', folderId: 0 }; showSaveModal.value = true; };
        const promptDeleteFolder = (folder) => { deleteModal.value = { show: true, id: folder.id, name: folder.name }; };
        const clearHistory = async () => { if(confirm('Clear all?')) { history.value = []; await apiCall('history_clear', {}, 'POST'); } };
        
        const decodedBody = computed(() => { if (!res.value.body) return ''; try { return decodeURIComponent(escape(window.atob(res.value.body))); } catch (e) { return "Binary data"; } });
        const highlightedBody = computed(() => { let txt = decodedBody.value; if(!txt) return ''; let formatted = txt; let lang = 'plaintext'; try { const jsonObj = JSON.parse(txt); if (window.js_beautify) { formatted = window.js_beautify(JSON.stringify(jsonObj), { indent_size: 2 }); lang = 'json'; } else { formatted = JSON.stringify(jsonObj, null, 2); } } catch (e) { if (txt.trim().startsWith('<')) { if (window.html_beautify) { formatted = window.html_beautify(txt, { indent_size: 2, wrap_line_length: 80 }); lang = 'xml'; } } } if (window.hljs) { if (lang !== 'plaintext') return window.hljs.highlight(formatted, { language: lang }).value; else return window.hljs.highlightAuto(formatted).value; } return formatted.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;"); });
        const previewHtml = computed(() => { const txt = decodedBody.value; if (!txt) return ''; if (txt.includes('<html') || txt.includes('<!DOCTYPE') || txt.includes('<!doctype')) { const baseTag = `<base href="${req.value.url}" target="_blank">`; if (txt.includes('<head>')) return txt.replace('<head>', `<head>${baseTag}`); return baseTag + txt; } return txt; });
        const isImage = computed(() => res.value.contentType && res.value.contentType.startsWith('image/'));
        const previewDataUrl = computed(() => isImage.value ? `data:${res.value.contentType};base64,${res.value.body}` : '');
        const updateUrlFromParams = () => { let baseUrl = req.value.url.split('?')[0]; const p = new URLSearchParams(); req.value.params.forEach(i => { if(i.key) p.append(i.key, i.value); }); const qs = p.toString(); req.value.url = qs ? `${baseUrl}?${qs}` : baseUrl; };
        const onUrlChange = () => { if(!req.value.url.includes('?')) return; const qs = req.value.url.split('?')[1]; const p = new URLSearchParams(qs); const arr = []; p.forEach((v,k)=>arr.push({key:k,value:v})); arr.push({key:'',value:''}); req.value.params = arr; };
        const addItem = (type) => { if(type==='Params') req.value.params.push({key:'',value:''}); if(type==='Headers') req.value.headers.push({key:'',value:''}); };
        const removeItem = (type, i) => { if(type==='Params') { req.value.params.splice(i,1); updateUrlFromParams(); } if(type==='Headers') req.value.headers.splice(i,1); };
        
        const downloadResponse = () => { if (!res.value.body) return; let ext = 'txt'; const ct = res.value.contentType || ''; if (ct.includes('json')) ext = 'json'; else if (ct.includes('html')) ext = 'html'; else if (ct.includes('image')) ext = ct.split('/')[1]; const link = document.createElement("a"); if (res.value.isBase64) { const byteCharacters = atob(res.value.body); const byteNumbers = new Array(byteCharacters.length); for (let i = 0; i < byteCharacters.length; i++) byteNumbers[i] = byteCharacters.charCodeAt(i); const blob = new Blob([new Uint8Array(byteNumbers)], {type: ct}); link.href = URL.createObjectURL(blob); } else { link.href = 'data:text/plain;charset=utf-8,' + encodeURIComponent(decodedBody.value); } link.download = `response_${Date.now()}.${ext}`; link.click(); };

        onMounted(() => {
            if (req.value.params.length === 0) req.value.params.push({key:'', value:''});
            if (req.value.headers.length === 0) req.value.headers.push({key:'', value:''});
            fetchData();
            fetchHistory();
        });

        return { 
            config, 
            sidebarTab, activeTab, resMode, bodyType, loading, 
            req, res, history, collections, authType, authToken, authUser, authPass,
            sendRequest, loadRequest, updateUrlFromParams, onUrlChange, addItem, removeItem, deleteHistory, clearHistory,
            methodColor, statusColor, formatSize, formatTime, 
            decodedBody, prettyBody: highlightedBody, highlightedBody, previewHtml, isImage, previewDataUrl, downloadResponse,
            storageStats, folders, folderTree, createFolder: startCreate, promptDeleteFolder, confirmDeleteFolder, deleteCollection, showSaveModal, saveForm, openSaveModal, confirmSave, deleteModal,
            creatingFolderId, startCreate, cancelCreate, confirmCreate 
        };
    }
}).mount('#app');
</script>
</body>
</html>