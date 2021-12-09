<?php
  require GETENV('GAME_ROOT').'/middlewares/initialize.php';

  $PAGE_SETTING['TITLE'] = 'ルールブック';

?>
<?php require GETENV('GAME_ROOT').'/components/header.php'; ?>
<style>

.rulebook-table {
  width: 100%;
  border-top: 1px solid #444;
  border-bottom: 1px solid #444;
  border-spacing: 0 10px;
  margin: 30px 0;
}

.rulebook-table th {
  width: 130px;
  text-align: center;
  font-weight: bold;
}

.rulebook-table td {
  padding: 4px 10px;
  border-left: 1px solid rgb(146, 136, 136);
}

.rulebook-list {
  margin: 30px 10px;
  border-top: 1px solid #444;
  list-style-type: none;
}

.rulebook-list li {
  padding: 15px;
  color: #777;
  font-weight: bold;
  border-bottom: 1px solid #444;
}

</style>
<?php require GETENV('GAME_ROOT').'/components/header_end.php'; ?>

<h1>ルールブック</h1>

<section>
  <h2>ゲームの進め方</h2>

  <p>
    Teiki Adventureはオリジナルキャラクターを1人1キャラクター登録し、1日1回配布されるAPで「探索」を行い、1週間に1度更新される「物語」を進めることでゲームは進行していきます。
  </p>

  <p>
    探索では各ステージ規定の回数クリアすることでステージが開放されていき、物語では週ごとに決まったステージを攻略していきます。<br>
    それぞれのステージでは自キャラクター＋お気に入りキャラクターの中から連れ出した最大4人の最大5人PTで攻略します。
  </p>

  <h2>ステータス・スキル</h2>

  <p>
    このゲームではNPというポイントを使うことで能力値を割り振ってキャラクターを成長させることができます。<br>
    能力値には以下の5種類があります。
  </p>

  <table class="rulebook-table">
    <tbody>
      <tr>
        <th>ATK</th>
        <td>高いほど敵に与えるダメージが大きくなる。</td>
      </tr>
      <tr>
        <th>AGI</th>
        <td>高いほど行動順が早くなる他、AGI％の確率で行動時にもう1回攻撃できる。</td>
      </tr>
      <tr>
        <th>DEX</th>
        <td>DEX％の確率でクリティカルになりダメージが2倍になる。</td>
      </tr>
      <tr>
        <th>DEF</th>
        <td>与えるヘイトの量がDEF倍になる。</td>
      </tr>
      <tr>
        <th>MND</th>
        <td>高いほど回復時に回復できるHPの量が多くなる。</td>
      </tr>
    <tbody>
  </table>
  
  <p>
    また、能力値を高めていくことでスキルを獲得することができます。<br>
    スキルには行動の際に発動するアクティブスキル、条件を満たした際に確率で発動するパッシブスキルがあります。<br>
    アクティブスキルはスキル設定の上から実行判断されていき、条件を満たしたものがあれば発動します。条件を満たすものがない場合、通常攻撃が行われます。
  </p>
</section>

<section>
  <h2>画像設定</h2>

  <p>
    ゲーム内の各箇所ではアイコンやプロフィール画像を登録することができます。<br>
    アイコンは横60px/縦60px、プロフィール画像は横400px/縦600pxで表示されます。
  </p>
</section>
  
<section>
  <h2>文字装飾</h2>

  <p>
    キャラクターのプロフィール文、トークルームの説明文は以下の書式で装飾することができます。<br>
    各書式の英大文字は小文字でも構いません。
  </p>

  <table class="rulebook-table">
    <tbody>
      <tr>
        <th>&lt;S1&gt;～&lt;/S1&gt;</th>
        <td>～の内容を小さく表示します。<br>数字部分は1～5を設定でき、数字が大きいほどより小さい文字になります。</td>
      </tr>
      <tr>
        <th>&lt;L1&gt;～&lt;/L1&gt;</th>
        <td>～の内容を大きく表示します。<br>数字部分は1～5を設定でき、数字が大きいほどより大きい文字になります。</td>
      </tr>
      <tr>
        <th>&lt;B&gt;～&lt;/B&gt;</th>
        <td>～の内容を太字で表示します。</td>
      </tr>
      <tr>
        <th>&lt;I&gt;～&lt;/I&gt;</th>
        <td>～の内容を斜体で表示します。</td>
      </tr>
      <tr>
        <th>&lt;S&gt;～&lt;/S&gt;</th>
        <td>～の内容を取り消し線付きで表示します。</td>
      </tr>
      <tr>
        <th>&lt;U&gt;～&lt;/U&gt;</th>
        <td>～の内容を下線付きで表示します。</td>
      </tr>
    <tbody>
  </table>

  <p>
    トークルームのメッセージでは上記の書式に加え、以下の書式でダイスを振ることができます。<br>
    各書式の英大文字は小文字でも構いません。
  </p>

  <table class="rulebook-table">
    <tbody>
      <tr>
        <th>&lt;1D6&gt;</th>
        <td>6面ダイスを振った結果を表示します。</td>
      </tr>
      <tr>
        <th>&lt;1D100&gt;</th>
        <td>100面ダイスを振った結果を表示します。</td>
      </tr>
    <tbody>
  </table>
</section>

<section>
  <h2>ミュート・ブロック</h2>

  <p>
    ミュートしている場合は以下のような挙動となります。

    <ul class="rulebook-list">
      <li>トークルームの全体・お気に入り・ツリー表示で相手が表示されなくなります。</li>
    </ul>
  </p>

  <p>
    ブロックしている、あるいはブロックされている場合は以下のような挙動となります。

    <ul class="rulebook-list">
      <li>お互いにお気に入りが行えなくなります。<br>ブロックする/される際にお気に入りを行っていた場合は解除されます。</li>
      <li>トークルームで相手が表示されなくなります。</li>
      <li>相手にダイレクトメッセージが送信できなくなります。</li>
      <li>相手に新たにアイテムの送付が行えなくなります。</li>
      <li>フリーマーケットで相手の出品したアイテムが表示されなくなります。</li>
    </ul>
  </p>
</section>

<?php require GETENV('GAME_ROOT').'/components/footer.php'; ?>