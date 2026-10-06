<?php
namespace App\Controllers;
use Mini\Database as DB;
use App\Services\Stock;
use App\Services\ListPage;
final class AppController {
    private function lists(): array { return ['warehouses'=>DB::fetchAll('SELECT * FROM warehouses ORDER BY name'),'riceTypes'=>DB::fetchAll('SELECT * FROM rice_types ORDER BY name'),'suppliers'=>DB::fetchAll('SELECT * FROM suppliers ORDER BY name')]; }
    public function dashboard(): void {
        if (!can('inventory.view')) { foreach (['purchases.view'=>'purchases','reports.view'=>'reports','purchases.create'=>'purchase/new','transfers.create'=>'transfer','production.create'=>'production','settings.manage'=>'warehouses','suppliers.credit'=>'supplier-credits','users.manage'=>'users'] as $p=>$path) if(can($p)) redirect($path); view('restricted',['title'=>'Welcome']); return; }
        $stats = ['stock'=>DB::fetchValue('SELECT COALESCE(SUM(quantity),0) FROM inventory'),'warehouses'=>DB::fetchValue('SELECT COUNT(*) FROM warehouses'),'production'=>DB::fetchValue("SELECT COALESCE(SUM(quantity),0) FROM movements WHERE kind='production' AND occurred_on BETWEEN ? AND ?",[date('Y-m-01'),date('Y-m-t')])];
        if(can('reports.view')) $stats['purchases']=DB::fetchValue('SELECT COALESCE(SUM(amount),0) FROM purchases WHERE purchased_on BETWEEN ? AND ?',[date('Y-m-01'),date('Y-m-t')]);
        view('dashboard',['title'=>'Overview','stats'=>$stats,'balances'=>DB::fetchAll('SELECT w.id,w.name,w.location,COALESCE(SUM(i.quantity),0) quantity,COUNT(i.rice_type_id) varieties FROM warehouses w LEFT JOIN inventory i ON i.warehouse_id=w.id GROUP BY w.id,w.name,w.location ORDER BY w.name'),'movements'=>$this->movements(8),'chart'=>can('reports.view')?$this->reportData(date('Y-01-01'),date('Y-12-31'),0,'month'):null]);
    }
    private function movements(int $limit = 50): array { return DB::fetchAll("SELECT m.*,CASE WHEN m.kind='purchase' THEN (SELECT version FROM purchases WHERE id=m.purchase_id) ELSE (SELECT version FROM movements WHERE id=m.operation_id) END operation_version,w.name warehouse,d.name destination,r.name rice,u.name recorded_by FROM movements m JOIN warehouses w ON w.id=m.warehouse_id LEFT JOIN warehouses d ON d.id=m.destination_id JOIN rice_types r ON r.id=m.rice_type_id JOIN users u ON u.id=m.user_id ORDER BY m.id DESC LIMIT " . $limit); }
    public function inventory(): void {
        $warehouses=DB::fetchAll('SELECT id,name FROM warehouses ORDER BY name,id');
        $rows=DB::fetchAll('SELECT r.id,r.name rice,COALESCE(SUM(i.quantity),0) total FROM rice_types r LEFT JOIN inventory i ON i.rice_type_id=r.id GROUP BY r.id,r.name ORDER BY r.name,r.id');
        $balances=[];
        foreach(DB::fetchAll('SELECT warehouse_id,rice_type_id,quantity FROM inventory') as $balance) $balances[(int)$balance['rice_type_id']][(int)$balance['warehouse_id']]=$balance['quantity'];
        view('inventory',compact('warehouses','rows','balances')+['title'=>'Inventory']);
    }
    public function stockMovements(): void {
        $pagination = new ListPage('stock-movements');
        $query=$pagination->text('q'); $warehouse=$pagination->id('warehouse'); $rice=$pagination->id('rice_type');
        $kind=$pagination->choice('kind',['purchase','transfer','production']);
        [$from,$to]=$pagination->dates(); $where=['1=1']; $bind=[];
        if($query!=='') { $where[]="(m.notes LIKE ? ESCAPE '!' OR r.name LIKE ? ESCAPE '!' OR u.name LIKE ? ESCAPE '!' OR p.reference LIKE ? ESCAPE '!')"; array_push($bind,...array_fill(0,4,ListPage::like($query))); }
        if($warehouse) { $where[]='(m.warehouse_id=? OR m.destination_id=?)'; array_push($bind,$warehouse,$warehouse); }
        if($rice) { $where[]='m.rice_type_id=?'; $bind[]=$rice; }
        if($kind) { $where[]='m.kind=?'; $bind[]=$kind; }
        if($from) { $where[]='m.occurred_on>=?'; $bind[]=$from; }
        if($to) { $where[]='m.occurred_on<=?'; $bind[]=$to; }
        if($pagination->errors) $where[]='1=0';
        $join=' FROM movements m JOIN warehouses w ON w.id=m.warehouse_id LEFT JOIN warehouses d ON d.id=m.destination_id JOIN rice_types r ON r.id=m.rice_type_id JOIN users u ON u.id=m.user_id LEFT JOIN purchases p ON p.id=m.purchase_id WHERE '.implode(' AND ',$where);
        $limit=$pagination->limit((int)DB::fetchValue('SELECT COUNT(*)'.$join,$bind));
        $movements=DB::fetchAll("SELECT m.*,CASE WHEN m.kind='purchase' THEN (SELECT version FROM purchases WHERE id=m.purchase_id) ELSE (SELECT version FROM movements WHERE id=m.operation_id) END operation_version,w.name warehouse,d.name destination,r.name rice,u.name recorded_by".$join.' ORDER BY m.occurred_on DESC,m.id DESC'.$limit,$bind);
        view('stock-movements',$this->lists()+compact('movements','pagination')+['title'=>'Stock movement ledger']);
    }
    public function purchases(): void {
        $pagination=new ListPage('purchases');
        $query=$pagination->text('q'); $warehouse=$pagination->id('warehouse'); $supplier=$pagination->id('supplier'); $rice=$pagination->id('rice_type');
        [$from,$to]=$pagination->dates(); $where=['1=1']; $bind=[];
        if($query!=='') { $where[]="(p.reference LIKE ? ESCAPE '!' OR s.name LIKE ? ESCAPE '!' OR EXISTS (SELECT 1 FROM purchase_items pi JOIN rice_types rt ON rt.id=pi.rice_type_id WHERE pi.purchase_id=p.id AND rt.name LIKE ? ESCAPE '!'))"; array_push($bind,...array_fill(0,3,ListPage::like($query))); }
        foreach(['warehouse_id'=>$warehouse,'supplier_id'=>$supplier] as $column=>$id) if($id) { $where[]='p.'.$column.'=?'; $bind[]=$id; }
        if($rice) { $where[]='EXISTS (SELECT 1 FROM purchase_items pi WHERE pi.purchase_id=p.id AND pi.rice_type_id=?)'; $bind[]=$rice; }
        if($from) { $where[]='p.purchased_on>=?'; $bind[]=$from; }
        if($to) { $where[]='p.purchased_on<=?'; $bind[]=$to; }
        if($pagination->errors) $where[]='1=0';
        $join=' FROM purchases p JOIN suppliers s ON s.id=p.supplier_id JOIN warehouses w ON w.id=p.warehouse_id WHERE '.implode(' AND ',$where);
        $limit=$pagination->limit((int)DB::fetchValue('SELECT COUNT(*)'.$join,$bind));
        $rows=DB::fetchAll("SELECT p.*,s.name supplier,w.name warehouse,(SELECT GROUP_CONCAT(rt.name ORDER BY pi.id SEPARATOR ', ') FROM purchase_items pi JOIN rice_types rt ON rt.id=pi.rice_type_id WHERE pi.purchase_id=p.id) rice,(SELECT COUNT(*) FROM purchase_items pi WHERE pi.purchase_id=p.id) item_count".$join.' ORDER BY p.purchased_on DESC,p.id DESC'.$limit,$bind);
        view('purchases',$this->lists()+compact('rows','pagination')+['title'=>'Purchases','count'=>$pagination->count]);
    }
    public function purchaseForm(): void { view('purchase-form',$this->lists()+['title'=>'New purchase','requestKey'=>bin2hex(random_bytes(32))]); }
    public function transferForm(): void { view('production-form',$this->lists()+['title'=>'Transfer stock','kind'=>'transfer','balances'=>DB::fetchAll('SELECT * FROM inventory'),'requestKey'=>bin2hex(random_bytes(32))]); }
    public function productionForm(): void { view('production-form',$this->lists()+['title'=>'Go to production','balances'=>DB::fetchAll('SELECT * FROM inventory'),'requestKey'=>bin2hex(random_bytes(32))]); }
    private function stockForm(string $kind): void { view('stock-form',array_merge($this->lists(),['title'=>['purchase'=>t('New purchase'),'transfer'=>t('Transfer stock'),'production'=>t('Go to production')][$kind],'kind'=>$kind,'balances'=>DB::fetchAll('SELECT * FROM inventory'),'requestKey'=>bin2hex(random_bytes(32))])); }
    public function savePurchase(): void { $this->saveStock('purchase'); }
    public function saveTransfer(): void { $this->saveStock('transfer'); }
    public function saveProduction(): void { $this->saveStock('production'); }
    private function saveStock(string $kind): void {
        $path=['purchase'=>'purchase/new','transfer'=>'transfer','production'=>'production'][$kind];
        try {
            $data=[]; foreach(['warehouse_id','rice_type_id','quantity','occurred_on','notes','request_key','destination_id','supplier_id','unit_price'] as $k) $data[$k]=input($k);
            if($kind==='purchase') { $data['amount_paid']=input('amount_paid'); $data['weight_lb']=input('weight_lb'); }
            if(array_key_exists('items',$_POST))$data['items']=$_POST['items'];
            if (strlen($data['notes'])>4000) throw new \InvalidArgumentException(t('Notes must be less than 4,000 characters.'));
            $id=Stock::record($kind,$data,(int)current_user()['id']);
            if ($kind==='purchase' && can('purchases.view')) redirect('voucher/'.$id,t('Purchase recorded and warehouse balance updated.'));
            redirect(can('inventory.view')?'inventory':$path,t('{operation} recorded successfully.',['operation'=>t(ucfirst($kind))]));
        } catch (\InvalidArgumentException $e) {
            $_SESSION['old']=array_filter($_POST,fn($v,$k)=>is_scalar($v) && !in_array($k,['_token','request_key']),ARRAY_FILTER_USE_BOTH);
            if(isset($_POST['items']) && is_array($_POST['items']))$_SESSION['old']['items']=array_values(array_map(fn($row)=>is_array($row)?array_filter($row,'is_scalar'):[],array_slice($_POST['items'],0,50)));
            if($kind==='purchase')$_SESSION['old']['purchase_step']=str_starts_with($e->getMessage(),t('Item '))||str_contains($e->getMessage(),t('rice type'))?1:(str_contains($e->getMessage(),t('Paid amount'))?2:0);
            if($kind==='production')$_SESSION['old']['production_step']=str_starts_with($e->getMessage(),t('Item '))||str_contains($e->getMessage(),t('rice type'))?1:0;
            if($kind==='transfer')$_SESSION['old']['transfer_step']=str_starts_with($e->getMessage(),t('Item '))||str_contains($e->getMessage(),t('rice type'))?1:0;
            redirect($path,$e->getMessage(),'error');
        } catch (\PDOException $e) {
            if (($e->errorInfo[1]??0)===1062) {
                $existing=DB::fetch('SELECT id FROM purchases WHERE request_key=?',[input('request_key')]);
                if ($existing && can('purchases.view')) redirect('voucher/'.$existing['id'],t('This purchase was already recorded.'));
                redirect($path,t('This submission was already recorded.'),'warning');
            } throw $e;
        }
    }
    public function voucher(string $id): void {
        $purchase=DB::fetch('SELECT p.*,s.name supplier,s.phone,s.address,w.name warehouse,w.location,r.name rice,u.name recorded_by FROM purchases p JOIN suppliers s ON s.id=p.supplier_id JOIN warehouses w ON w.id=p.warehouse_id JOIN rice_types r ON r.id=p.rice_type_id JOIN users u ON u.id=p.user_id WHERE p.id=?',[(int)$id]);
        if (!$purchase) { http_response_code(404); view('restricted',['title'=>'Voucher not found']); return; } view('voucher',['title'=>'Purchase voucher','p'=>$purchase,'items'=>\App\Services\Purchase::items((int)$id)]);
    }
    private function reportData(string $start,string $end,int $warehouse,string $group): array {
        $format=['day'=>'%Y-%m-%d','month'=>'%Y-%m','year'=>'%Y'][$group];
        $rows=DB::fetchAll("SELECT DATE_FORMAT(purchased_on,'$format') period,SUM(amount) amount,SUM(quantity) quantity,COUNT(*) purchases FROM purchases WHERE purchased_on BETWEEN ? AND ? AND (?=0 OR warehouse_id=?) GROUP BY period ORDER BY period",[$start,$end,$warehouse,$warehouse]);
        $indexed=array_column($rows,null,'period'); $periods=[]; $date=new \DateTimeImmutable($start); $finish=new \DateTimeImmutable($end);
        if($group==='month') $date=$date->modify('first day of this month'); if($group==='year') $date=$date->setDate((int)$date->format('Y'),1,1);
        for(;$date<=$finish;$date=$date->modify('+1 '.$group)) { $key=$date->format(['day'=>'Y-m-d','month'=>'Y-m','year'=>'Y'][$group]); $periods[]=$indexed[$key]??['period'=>$key,'amount'=>0,'quantity'=>0,'purchases'=>0]; } return $periods;
    }
    public function reports(): void {
        $error=null; $group=is_string($_GET['group']??null)?$_GET['group']:'month'; $group=in_array($group,['day','month','year'],true)?$group:'month'; $warehouse=max(0,(int)($_GET['warehouse']??0));
        try {
            $start=valid_date(is_string($_GET['start']??null)?$_GET['start']:date('Y-01-01')); $end=valid_date(is_string($_GET['end']??null)?$_GET['end']:date('Y-12-31'));
            if($start>$end) throw new \InvalidArgumentException(t('Start date must be before the end date.')); $days=(new \DateTimeImmutable($start))->diff(new \DateTimeImmutable($end))->days;
            if($days>36525 || ($group==='day' && $days>730)) throw new \InvalidArgumentException(t('Use monthly or yearly grouping for long ranges (daily reports support up to two years).'));
            if($warehouse && !DB::fetch('SELECT id FROM warehouses WHERE id=?',[$warehouse])) throw new \InvalidArgumentException(t('Warehouse does not exist.'));
        } catch (\InvalidArgumentException $e) { $error=$e->getMessage(); $start=date('Y-01-01');$end=date('Y-12-31');$group='month';$warehouse=0; }
        $rows=$this->reportData($start,$end,$warehouse,$group);
        $byWarehouse=DB::fetchAll('SELECT w.name,SUM(p.amount) amount,SUM(p.quantity) quantity,COUNT(*) purchases FROM purchases p JOIN warehouses w ON w.id=p.warehouse_id WHERE purchased_on BETWEEN ? AND ? AND (?=0 OR warehouse_id=?) GROUP BY w.id,w.name ORDER BY amount DESC',[$start,$end,$warehouse,$warehouse]);
        view('reports',array_merge($this->lists(),compact('start','end','warehouse','group','rows','byWarehouse','error'),['title'=>'Purchase reports']));
    }
    public function settings(): void { redirect('warehouses'); }
    public function saveSetting(): void {
        try {
            $type=input('type'); $name=input('name'); if(!in_array($type,['warehouses','rice_types','suppliers'],true) || !$name || strlen($name)>120) throw new \InvalidArgumentException(t('Enter a name of up to 120 characters.')); $data=['name'=>$name];
            foreach(['warehouses'=>['location'],'suppliers'=>['phone','address'],'rice_types'=>[]][$type] as $k) { $v=input($k); if(strlen($v)>($k==='phone'?50:255)) throw new \InvalidArgumentException(t('{field} is too long.',['field'=>t(ucfirst($k))])); $data[$k]=$v; }
            DB::insert($type,$data); redirect('settings',t('Saved successfully.'));
        } catch (\InvalidArgumentException $e) { redirect('settings',$e->getMessage(),'error'); } catch (\PDOException $e) { if(($e->errorInfo[1]??0)===1062) redirect('settings',t('That name already exists.'),'error'); throw $e; }
    }
    public function users(): void { view('users',['title'=>'Team & permissions','users'=>DB::fetchAll('SELECT id,name,email,role,permissions,active FROM users ORDER BY role,name')]); }
    public function saveUser(): void {
        try {
            $id=(int)input('id','0'); $name=input('name');$email=strtolower(input('email'));$password=input('password');
            if(!$name || strlen($name)>120 || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>190) throw new \InvalidArgumentException(t('Enter a name and valid email address.'));
            if((!$id || $password!=='') && (strlen($password)<10 || strlen($password)>72)) throw new \InvalidArgumentException(t('Password must contain 10–72 characters.'));
            $existing=$id?DB::fetch('SELECT * FROM users WHERE id=?',[$id]):null;
            if($id && (!$existing || $existing['role']==='owner')) throw new \InvalidArgumentException(t('Owner accounts cannot be changed through staff management.'));
            $submitted=$_POST['permissions']??[]; if(!is_array($submitted) || count(array_filter($submitted,'is_string'))!==count($submitted)) throw new \InvalidArgumentException(t('Invalid permissions.'));
            $allowed=array_diff(array_keys(permissions()),['users.manage']); $selected=array_values(array_intersect($allowed,$submitted));
            $data=['name'=>$name,'email'=>$email,'role'=>'staff','permissions'=>json_encode($selected),'active'=>input('active','0')==='1'?1:0];
            if($password!=='') $data['password']=password_hash($password,PASSWORD_DEFAULT); if($id) DB::update('users',$id,$data); else DB::insert('users',$data); redirect('users',t('Staff account saved.'));
        } catch(\InvalidArgumentException $e) { redirect('users',$e->getMessage(),'error'); } catch(\PDOException $e) { if(($e->errorInfo[1]??0)===1062) redirect('users',t('That email is already in use.'),'error'); throw $e; }
    }
}
