<?php
declare(strict_types=1);
namespace App\Controllers;
use Mini\Database as DB;
use App\Services\SupplierCredit;
use App\Services\ListPage;
use InvalidArgumentException;
use PDOException;

final class MasterDataController
{
    private const TYPES = [
        'warehouses'=>['table'=>'warehouses','title'=>'Warehouses','singular'=>'warehouse','fields'=>['location'=>255]],
        'rice-types'=>['table'=>'rice_types','title'=>'Rice types','singular'=>'rice type','fields'=>[]],
        'suppliers'=>['table'=>'suppliers','title'=>'Suppliers','singular'=>'supplier','fields'=>['phone'=>50,'address'=>255]],
    ];
    public function index(string $type, string $edit = '0'): void
    {
        $definition=self::TYPES[$type]; $record=null;
        if((int)$edit) {
            $record=DB::fetch('SELECT * FROM '.$definition['table'].' WHERE id=?',[(int)$edit]);
            if(!$record){http_response_code(404);view('restricted',['title'=>'Record not found']);return;}
        }
        $pagination=null;
        $query=ListPage::value('q');
        if($type==='suppliers') {
            $pagination=new ListPage('suppliers'); $query=$pagination->text('q');
            $credit=can('suppliers.credit');
            $balance=$credit?$pagination->choice('balance',['outstanding','settled']):'';
            $source=$credit?SupplierCredit::summarySql():'SELECT s.* FROM suppliers s';
            $where=" WHERE (s.name LIKE ? ESCAPE '!' OR s.phone LIKE ? ESCAPE '!' OR s.address LIKE ? ESCAPE '!')";
            if($balance==='outstanding')$where.=' AND s.balance>0';
            if($balance==='settled')$where.=' AND s.balance=0';
            $bind=array_fill(0,3,ListPage::like($query));
            $join=' FROM ('.$source.') s'.$where;
            $limit=$pagination->limit((int)DB::fetchValue('SELECT COUNT(*)'.$join,$bind));
            $rows=DB::fetchAll('SELECT s.*'.$join.' ORDER BY s.name,s.id'.$limit,$bind);
        } else {
            $rows=DB::fetchAll('SELECT * FROM '.$definition['table'].' ORDER BY name');
            if($query!=='') $rows=array_values(array_filter($rows,fn($row)=>stripos($row['name'].' '.($row['location']??''),$query)!==false));
        }
        $formError=$_SESSION['master_form_error'][$type]??null;
        unset($_SESSION['master_form_error'][$type]);
        view('master-data',compact('type','definition','record','rows','query','formError','pagination')+['title'=>$definition['title']]);
    }
    public function save(string $type): void
    {
        $definition=self::TYPES[$type]; $id=(int)input('id','0');
        try {
            $name=input('name');
            if($name==='' || strlen($name)>120) throw new InvalidArgumentException(t('Enter a name of up to 120 characters.'));
            $data=['name'=>$name];
            foreach($definition['fields'] as $field=>$limit){$value=input($field);if(strlen($value)>$limit)throw new InvalidArgumentException(t('{field} is too long.',['field'=>t(ucfirst($field))]));$data[$field]=$value;}
            if($id){
                if(!DB::fetch('SELECT id FROM '.$definition['table'].' WHERE id=?',[$id])) throw new InvalidArgumentException(t('This record no longer exists.'));
                DB::update($definition['table'],$id,$data);
            } else DB::insert($definition['table'],$data);
            redirect($type,t($id?'{type} updated.':'{type} created.',['type'=>t(ucfirst($definition['singular']))]));
        } catch(InvalidArgumentException $error){$this->failed($type,$id,$error->getMessage());}
        catch(PDOException $error){if(($error->errorInfo[1]??0)===1062)$this->failed($type,$id,t('That name already exists. Choose a different name.'));throw $error;}
    }
    private function failed(string $type,int $id,string $message): never
    {
        $_SESSION['old']=array_filter($_POST,'is_scalar');unset($_SESSION['old']['_token']);
        $_SESSION['master_form_error'][$type]=$message;
        redirect($type.($id?'/'.$id.'/edit':''),$message,'error');
    }
    public function delete(string $type,string $id): void
    {
        $definition=self::TYPES[$type];
        try {
            DB::transaction(function() use($definition,$type,$id): void {
                if(!DB::fetch('SELECT id FROM '.$definition['table'].' WHERE id=? FOR UPDATE',[(int)$id])) throw new InvalidArgumentException(t('This record no longer exists.'));
                if($type!=='suppliers') {
                    $column=$type==='warehouses'?'warehouse_id':'rice_type_id';
                    if(DB::fetch('SELECT quantity FROM inventory WHERE '.$column.'=? AND quantity>0 LIMIT 1',[(int)$id])) throw new InvalidArgumentException(t('This record holds stock and cannot be deleted.'));
                    DB::statement('DELETE FROM inventory WHERE '.$column.'=?',[(int)$id]);
                }
                DB::statement('DELETE FROM '.$definition['table'].' WHERE id=?',[(int)$id]);
            });
            redirect($type,t('{type} deleted.',['type'=>t(ucfirst($definition['singular']))]));
        } catch(InvalidArgumentException $error){redirect($type,$error->getMessage(),'error');}
        catch(PDOException $error){if(($error->errorInfo[1]??0)===1451)redirect($type,t('This record is used in purchases, stock movements or payments and cannot be deleted.'),'error');throw $error;}
    }
    public function credit(string $id): void
    {
        $supplier=DB::fetch('SELECT * FROM suppliers WHERE id=?',[(int)$id]);
        if(!$supplier){http_response_code(404);view('restricted',['title'=>'Supplier not found']);return;}
        $purchases=DB::fetchAll('SELECT p.*,w.name warehouse FROM purchases p JOIN warehouses w ON w.id=p.warehouse_id WHERE supplier_id=? ORDER BY purchased_on DESC,id DESC',[(int)$id]);
        $payments=DB::fetchAll('SELECT p.*,u.name recorded_by FROM supplier_payments p JOIN users u ON u.id=p.user_id WHERE supplier_id=? ORDER BY paid_on DESC,p.id DESC',[(int)$id]);
        $balance=SupplierCredit::balance((int)$id);
        view('supplier-credit',compact('supplier','purchases','payments','balance')+['title'=>'Supplier credit','requestKey'=>bin2hex(random_bytes(32))]);
    }
    public function payment(string $id): void
    {
        try {
            SupplierCredit::pay((int)$id,input('amount'),input('paid_on'),input('notes'),input('request_key'),(int)current_user()['id']);
            redirect('suppliers/'.$id.'/credit',t('Supplier payment recorded. Outstanding balance updated.'));
        } catch(InvalidArgumentException $error){
            $_SESSION['old']=array_filter($_POST,'is_scalar');unset($_SESSION['old']['_token'],$_SESSION['old']['request_key']);
            redirect('suppliers/'.$id.'/credit',$error->getMessage(),'error');
        }
    }
    public function credits(): void
    {
        view('supplier-credits',['title'=>'Supplier credit','rows'=>SupplierCredit::summaries()]);
    }
}
