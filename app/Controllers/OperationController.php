<?php
declare(strict_types=1);
namespace App\Controllers;
use App\Services\Operations;
use App\Services\Purchase;
use App\Services\StockBatch;
use Mini\Database as DB;
use InvalidArgumentException;

final class OperationController
{
    private function record(string $kind,string $id): ?array
    {
        $record=ctype_digit($id)&&strlen($id)<=18?Operations::find($kind,(int)$id):null;
        if(!$record){http_response_code(404);view('restricted',['title'=>'Record not found']);}
        return $record;
    }
    public function detail(string $kind,string $id): void
    {
        if(!$record=$this->record($kind,$id))return;
        $items=$kind==='purchase'?Purchase::items((int)$id):DB::fetchAll('SELECT m.*,r.name rice FROM movements m JOIN rice_types r ON r.id=m.rice_type_id WHERE m.operation_id=? ORDER BY m.id',[(int)$id]);
        $warehouse=DB::fetchValue('SELECT name FROM warehouses WHERE id=?',[$record['warehouse_id']]);
        $destination=$kind==='transfer'?DB::fetchValue('SELECT name FROM warehouses WHERE id=?',[$record['destination_id']]):null;
        $supplier=$kind==='purchase'?DB::fetchValue('SELECT name FROM suppliers WHERE id=?',[$record['supplier_id']]):null;
        $recordedBy=DB::fetchValue('SELECT name FROM users WHERE id=?',[$record['user_id']]);
        $total=DB::fetchValue($kind==='purchase'?'SELECT SUM(quantity) FROM purchase_items WHERE purchase_id=?':'SELECT SUM(quantity) FROM movements WHERE operation_id=?',[(int)$id]);
        view('operation-detail',compact('kind','record','items','warehouse','destination','supplier','recordedBy','total')+['title'=>ucfirst($kind).' details']);
    }
    public function edit(string $kind,string $id): void
    {
        if(!$record=$this->record($kind,$id))return;
        $items=$kind==='purchase'?Purchase::items((int)$id):Operations::rows($kind,(int)$id);
        $defaults=$record+['occurred_on'=>$record['purchased_on']??$record['occurred_on'],'items'=>$items];
        $draft=$_SESSION['operation_draft'][$kind.':'.$id]??[];unset($_SESSION['operation_draft'][$kind.':'.$id]);
        $_SESSION['old']=$draft+$defaults;
        $balances=DB::fetchAll('SELECT * FROM inventory');
        // Wizard shows available stock with this operation's old effect removed.
        if($kind!=='purchase')foreach($items as $item)foreach($balances as &$balance) {
            if((int)$balance['rice_type_id']!==(int)$item['rice_type_id'])continue;
            $direction=(int)$balance['warehouse_id']===(int)$record['warehouse_id']?1:($kind==='transfer' && (int)$balance['warehouse_id']===(int)$record['destination_id']?-1:0);
            if($direction)$balance['quantity']=DB::fetchValue('SELECT CAST(? AS DECIMAL(24,3))+CAST(? AS DECIMAL(24,3))*?',[$balance['quantity'],$item['quantity'],$direction]);
        }
        unset($balance);
        view($kind==='purchase'?'purchase-form':'production-form',[
            'warehouses'=>DB::fetchAll('SELECT * FROM warehouses ORDER BY name'),'riceTypes'=>DB::fetchAll('SELECT * FROM rice_types ORDER BY name'),'suppliers'=>DB::fetchAll('SELECT * FROM suppliers ORDER BY name'),
            'kind'=>$kind,'title'=>'Edit '.$kind,'balances'=>$balances,'requestKey'=>bin2hex(random_bytes(32)),
            'editRecord'=>$record,'formAction'=>$kind.'/'.$id.'/edit','cancelPath'=>$kind.'/'.$id,
        ]);
    }
    public function update(string $kind,string $id): void
    {
        if(!$this->record($kind,$id))return;
        try {
            $data=[];foreach(['warehouse_id','supplier_id','destination_id','occurred_on','notes','amount_paid','request_key'] as $field)$data[$field]=input($field);
            $data['items']=$_POST['items']??[];
            if($kind==='purchase')Purchase::record($data,(int)current_user()['id'],(int)$id,(int)input('version'));
            else StockBatch::record($kind,$data,(int)current_user()['id'],(int)$id,(int)input('version'));
            redirect($kind.'/'.$id,t('{operation} updated. Stock balances have been adjusted.',['operation'=>t(ucfirst($kind))]));
        } catch(InvalidArgumentException $error) {
            $draft=array_filter($_POST,'is_scalar');unset($draft['_token'],$draft['request_key']);
            if(is_array($_POST['items']??null))$draft['items']=array_map(fn($row)=>is_array($row)?array_filter($row,'is_scalar'):[],array_slice($_POST['items'],0,50));
            $draft[($kind==='purchase'?'purchase':$kind).'_step']=str_starts_with($error->getMessage(),t('Item '))?1:0;
            $_SESSION['operation_draft'][$kind.':'.$id]=$draft;
            redirect($kind.'/'.$id.'/edit',$error->getMessage(),'error');
        }
    }
    public function delete(string $kind,string $id): void
    {
        if(!$this->record($kind,$id))return;
        try {
            Operations::delete($kind,(int)$id,(int)input('version'));
            redirect($kind==='purchase'?'purchases':'stock-movements',t('{operation} deleted. Stock balances have been adjusted.',['operation'=>t(ucfirst($kind))]));
        } catch(InvalidArgumentException $error) { redirect($kind.'/'.$id,$error->getMessage(),'error'); }
    }
}
